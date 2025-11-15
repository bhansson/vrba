<section class="relative py-24 px-6 lg:px-8 overflow-hidden">
    <!-- Gradient Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-brand-purple via-brand-blue to-brand-pink"></div>

    <!-- Animated Background Shapes -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none opacity-20">
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-white rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-white rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
    </div>

    <div class="relative mx-auto max-w-5xl text-center">
        <!-- Badge -->
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur-sm rounded-full border border-white/30 mb-8">
            <div class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
            </div>
            <span class="text-sm font-semibold text-white">Limited Time Offer</span>
        </div>

        <h2 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-white tracking-tight leading-tight">
            Ready to scale your catalog with AI?
        </h2>

        <p class="mt-6 text-xl sm:text-2xl text-white/90 max-w-3xl mx-auto leading-relaxed">
            Join marketing teams who've already automated their product content. Start free, no credit card required.
        </p>

        <!-- CTA Buttons -->
        <div class="mt-12 flex flex-col sm:flex-row gap-4 justify-center items-center">
            <a href="{{ route('register') }}" class="group relative inline-flex items-center justify-center px-10 py-5 text-lg font-bold text-brand-purple bg-white rounded-xl hover:scale-105 transition-all duration-300 shadow-2xl">
                <span class="flex items-center gap-2">
                    Start Free Trial
                    <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </span>
            </a>
            <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-8 py-5 text-lg font-semibold text-white hover:text-white/80 transition-colors">
                Already have an account? <span class="ml-2 underline">Log in</span>
            </a>
        </div>

        <!-- Trust Indicators -->
        <div class="mt-12 flex flex-col sm:flex-row items-center justify-center gap-8 text-white/90">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="font-medium">Free 14-day trial</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="font-medium">No credit card needed</span>
            </div>
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="font-medium">Cancel anytime</span>
            </div>
        </div>

        <!-- Footer Note -->
        <p class="mt-12 text-sm text-white/70">
            Trusted by e-commerce teams managing 500K+ products worldwide
        </p>
    </div>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-white py-12 px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Brand -->
            <div class="md:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-brand-gradient rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-bold">Magnifiq</span>
                </div>
                <p class="text-gray-400 max-w-md">
                    AI-powered product catalog management platform. Transform your product data into compelling marketing assets in seconds.
                </p>
            </div>

            <!-- Product -->
            <div>
                <h3 class="font-semibold mb-4">Product</h3>
                <ul class="space-y-2 text-gray-400">
                    <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
                    <li><a href="#how-it-works" class="hover:text-white transition-colors">How it Works</a></li>
                    <li><a href="#use-cases" class="hover:text-white transition-colors">Use Cases</a></li>
                </ul>
            </div>

            <!-- Company -->
            <div>
                <h3 class="font-semibold mb-4">Company</h3>
                <ul class="space-y-2 text-gray-400">
                    <li><a href="{{ route('login') }}" class="hover:text-white transition-colors">Login</a></li>
                    <li><a href="{{ route('register') }}" class="hover:text-white transition-colors">Sign Up</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 pt-8 border-t border-gray-800 text-center text-gray-400 text-sm">
            <p>&copy; {{ date('Y') }} Magnifiq. All rights reserved.</p>
        </div>
    </div>
</footer>
