<div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title w-400">{{$title}}</h1>
            </div>
            <div class="modal-body">
                {{$body}}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Anuluj
                </button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    Tak, usuń
                </button>
            </div>
        </div>
    </div>
</div>
