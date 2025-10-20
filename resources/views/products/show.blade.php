<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Product Details') }}
            </h2>
            <a href="{{ route('products.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                ← Back to products
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <livewire:product-show :productId="$product->id" />
    </div>
</x-app-layout>
