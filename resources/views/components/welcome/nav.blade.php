<nav x-data="{ scrolled: false }"
     @scroll.window="scrolled = window.pageYOffset > 50"
     :class="scrolled ? 'bg-white/80 backdrop-blur-lg shadow-sm' : 'bg-transparent'"
     class="fixed top-0 left-0 right-0 z-50 transition-all duration-300">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="flex items-center justify-between py-4">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="/" class="text-2xl font-bold text-gray-900">
                    Magnifiq
                </a>
            </div>

            <!-- Auth Links -->
            <div class="flex items-center gap-4">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                        Log In
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-brand-gradient rounded-lg hover:scale-105 transition-transform duration-200">
                        Sign Up
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>
