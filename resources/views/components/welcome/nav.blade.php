<nav x-data="{ scrolled: false, mobileMenuOpen: false }"
     @scroll.window="scrolled = window.pageYOffset > 50"
     :class="scrolled ? 'bg-white/95 backdrop-blur-xl shadow-lg' : 'bg-white/50 backdrop-blur-sm'"
     class="fixed top-0 left-0 right-0 z-50 transition-all duration-300">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        <div class="flex items-center justify-between py-5">
            <!-- Logo with Icon -->
            <div class="flex items-center gap-3">
                <a href="/" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 bg-brand-gradient rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-brand-purple via-brand-blue to-brand-pink">
                        Magnifiq
                    </span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center gap-8">
                <a href="#features" class="text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                    Features
                </a>
                <a href="#how-it-works" class="text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                    How It Works
                </a>
                <a href="#use-cases" class="text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                    Use Cases
                </a>
            </div>

            <!-- Auth Links -->
            <div class="flex items-center gap-4">
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden md:inline-flex items-center px-5 py-2.5 text-sm font-semibold text-white bg-brand-gradient rounded-xl hover:scale-105 hover:shadow-lg transition-all duration-200">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden md:block text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                        Log In
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center px-5 py-2.5 text-sm font-semibold text-white bg-brand-gradient rounded-xl hover:scale-105 hover:shadow-lg transition-all duration-200">
                        Start Free Trial
                    </a>
                @endauth

                <!-- Mobile Menu Button -->
                <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-gray-700 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        <path x-show="mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" x-cloak x-transition class="md:hidden py-4 border-t border-gray-200">
            <div class="flex flex-col gap-4">
                <a href="#features" @click="mobileMenuOpen = false" class="text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                    Features
                </a>
                <a href="#how-it-works" @click="mobileMenuOpen = false" class="text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                    How It Works
                </a>
                <a href="#use-cases" @click="mobileMenuOpen = false" class="text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                    Use Cases
                </a>
                @guest
                    <a href="{{ route('login') }}" class="text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors">
                        Log In
                    </a>
                @endguest
            </div>
        </div>
    </div>
</nav>
