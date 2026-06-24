<x-app-layout>

    <div class="page-bg-2"></div>
    @if (session('error'))
        <script>
            console.error(@json(session('error')));
        </script>
    @endif

    @include('components.breadcrumb')


    <div class="container py-2">

        <!-- FILTRY -->
        <div class="mb-3 row">
            <div class="col-md-4">
                <input type="text" id="search" class="form-control" placeholder="Szukaj...">
            </div>

            <div class="col-md-3">
                <select id="sort" class="form-select">
                    <option value="">Sortuj</option>
                    <option value="name">Imię i nazwisko</option>
                    <option value="email">Email</option>
                </select>
            </div>

            <div class="col-md-3">
                <select id="perPage" class="form-select">
                    <option value="10">10 na stronę</option>
                    <option value="50">50 na stronę</option>
                    <option value="100">100 na stronę</option>
                </select>
            </div>
        </div>


        <table class="table bg-white shadow-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th>Użytkownik</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody id="userTable"></tbody>
        </table>

        <nav>
            <ul class="pagination" id="pagination"></ul>
        </nav>
    </div>

    <script>
        const users = @json($users);

        let currentPage = 1;

        // 🔹 RENDER
        function render() {
            let filtered = [...users];

            const search = document.getElementById('search').value.toLowerCase();
            const sort = document.getElementById('sort').value;
            const perPage = parseInt(document.getElementById('perPage').value);

            // 🔍 SEARCH
            if (search) {
                filtered = filtered.filter(u =>
                    u.name.toLowerCase().includes(search) ||
                    u.email.toLowerCase().includes(search)
                );
            }

            // 🔃 SORT
            if (sort === 'name') {
                filtered.sort((a, b) => a.name.localeCompare(b.name));
            }
            if (sort === 'email') {
                filtered.sort((a, b) => a.email.localeCompare(b.email));
            }

            // 📄 PAGINATION
            const start = (currentPage - 1) * perPage;
            const paginated = filtered.slice(start, start + perPage);

            const table = document.getElementById('userTable');
            table.innerHTML = '';
            //console.log(paginated);
            paginated.forEach(u => {
                if (!u.avatar) u.avatar = 'img/noUser.jpg';
                table.innerHTML += `
                <tr class="align-middle">
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${u.avatar}" class="user-avatar me-3">
                            <div><strong>${u.name}</strong></div>
                        </div>
                    </td>
                    <td>${u.email}</td>
                    <td>
                        ${u.is_approved
                            ? '<i class="bi bi-check-circle status-icon status-active" title="Konto zatwierdzone" data-bs-toggle="tooltip"></i>'
                            : '<i class="bi bi-x-circle status-icon status-blocked" title="Oczekuje na zatwierdzenie" data-bs-toggle="tooltip"></i>'
                        }

                        ${u.is_banned
                            ? '<i class="bi bi-person-x status-icon status-blocked" title="Użytkownik zablokowany" data-bs-toggle="tooltip"></i>'
                            : ''
                        }

                        ${u.is_online
                            ? '<i class="bi bi-circle-fill status-icon status-online" title="Online" data-bs-toggle="tooltip"></i>'
                            : ''
                        }
                    </td>
                    <td>
                        <i class="bi bi-lock action-icon text-warning" onclick="blockUser(${u.id})"></i>
                        <i class="bi bi-arrow-clockwise action-icon text-info" onclick="resetPassword(${u.id})"></i>
                        <i class="bi bi-pencil action-icon text-primary" onclick="editUser(${u.id})"></i>
                        <i class="bi bi-trash action-icon text-danger" onclick="deleteUser(${u.id})"></i>
                        <i class="bi bi-slash-circle action-icon text-dark" onclick="banUser(${u.id})"></i>
                    </td>
                </tr>`;
            });

            renderPagination(filtered.length, perPage);
        }

        // 🔹 PAGINACJA
        function renderPagination(total, perPage) {
            const pages = Math.ceil(total / perPage);
            const pag = document.getElementById('pagination');
            pag.innerHTML = '';

            for (let i = 1; i <= pages; i++) {
                pag.innerHTML += `
                <li class="page-item ${i===currentPage?'active':''}">
                    <a class="page-link" href="#" onclick="goPage(${i})">${i}</a>
                </li>`;
            }
        }

        function goPage(p) {
            currentPage = p;
            render();
        }

        // 🔹 AKCJE
        function blockUser(id) {
            alert("Blokuj " + id);
        }

        function resetPassword(id) {
            alert("Reset " + id);
        }

        function editUser(id) {
            alert("Edytuj " + id);
        }

        function deleteUser(id) {
            alert("Usuń " + id);
        }

        function banUser(id) {
            alert("Ban " + id);
        }

        // 🔹 EVENTY
        document.getElementById('search').addEventListener('input', () => {
            currentPage = 1;
            render();
        });
        document.getElementById('sort').addEventListener('change', render);
        document.getElementById('perPage').addEventListener('change', () => {
            currentPage = 1;
            render();
        });

        // INIT
        render();

        // Wywołaj to po wstawieniu danych do tabeli
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    </script>

</x-app-layout>
