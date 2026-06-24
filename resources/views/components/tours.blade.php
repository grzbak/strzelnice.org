<section id="offers" class="container mx-auto py-16">
  <h2 class="text-3xl font-bold text-center mb-8">Nasze Wycieczki</h2>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    @foreach($tours as $tour)
      <div class="border rounded-lg shadow hover:shadow-lg transition">
        <img src="{{ $tour['image'] }}" alt="{{ $tour['title'] }}" class="h-48 w-full object-cover rounded-t-lg">
        <div class="p-4">
          <h3 class="text-xl font-semibold">{{ $tour['title'] }}</h3>
          <p class="text-sm text-gray-600">{{ $tour['description'] }}</p>
          <div class="mt-4 flex justify-between items-center">
            <span class="font-bold text-blue-600">{{ $tour['price'] }} zł</span>
            <a href="#" class="text-sm text-white bg-blue-600 px-3 py-1 rounded hover:bg-blue-700">Zarezerwuj</a>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</section>
