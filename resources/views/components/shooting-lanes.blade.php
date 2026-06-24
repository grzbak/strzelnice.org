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




 <div class="py-5 row row-cols-1 row-cols-lg-3 align-items-stretch g-4">
     @php
         $canDelete = $shootingRange->lanes->count() > 1;
     @endphp

     @foreach ($shootingRange->lanes as $lane)
         <x-shooting-lane :lane="$lane" :permissionDelete="$canDelete" />
     @endforeach

 </div>

 <button type="button" id="add-lane" class="px-4 py-2 bg-green-600 rounded text-dark" x-data
     x-on:click="$dispatch('open-modal', 'confirm-addLane')">
     <i class="bi bi-file-earmark-plus"></i> Dodaj oś
 </button>

 @push('modals')
     <!-- dodawanie nowej osi-->
     <x-modal name="confirm-addLane">
         <div class="p-6">
             <h2 class="text-lg font-semibold text-gray-900">
                 Dodawanie nowej osi
             </h2>
             <form method="POST" action="{{ route('shooting.lanes.store') }}">
                 @csrf
                 <x-form-lanes name="confirm-addLane">
                 </x-form-lanes>
             </form>
         </div>
     </x-modal>
 @endpush

 @if ($errors->any())
     <script>
         window.addEventListener('DOMContentLoaded', () => {
             window.dispatchEvent(
                 new CustomEvent('open-modal', {
                     detail: 'confirm-addLane'
                 })
             )
         })
     </script>
 @endif
