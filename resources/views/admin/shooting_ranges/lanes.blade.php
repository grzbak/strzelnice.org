<x-app-layout>

    @php
        $oldLanes = $shootingRange->lanes;
    @endphp

    <div class="page-bg-2"></div>
    @if (session('error'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast(@json(session('error')), 'error');
            });
        </script>
    @endif
    @if (session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast(@json(session('success')), 'success');
            });
        </script>
    @endif

    @include('components.visiting-card')



    <div class="flex flex-col items-center ">
        <div class="container px-6 py-6 mt-1 mb-4 overflow-hidden bg-white shadow-md sm:max-w-3xl sm:rounded-lg">



            <h1 class="mb-6 text-xl font-semibold text-center">
                {{ $shootingRange->name }} - osie strzeleckie
            </h1>

            <x-shooting-lanes :shootingRange="$shootingRange" />

        </div>
    </div>

    @stack('modals')

</x-app-layout>
