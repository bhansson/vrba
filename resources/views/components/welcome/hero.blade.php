<section class="relative pt-32 pb-20 px-6 lg:px-8 overflow-hidden">
    <!-- Gradient Mesh Background -->
    <div class="absolute top-0 right-0 w-1/2 h-full bg-brand-gradient-light blur-3xl opacity-30 pointer-events-none"></div>

    <div class="relative mx-auto max-w-7xl">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-12 items-center">
            <!-- Left: Content (60% on desktop) -->
            <div class="lg:col-span-3">
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-gray-900 tracking-tight leading-tight">
                    Product catalogs that think for themselves
                </h1>

                <p class="mt-6 text-xl text-gray-600 leading-relaxed">
                    Automate product imports, generate marketing copy, and create photorealistic images—all powered by AI. Everything your e-commerce team needs in one intelligent platform.
                </p>

                <!-- CTA Buttons -->
                <div class="mt-10 flex flex-col sm:flex-row gap-4">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-brand-gradient rounded-lg hover:scale-105 transition-transform duration-200 shadow-lg">
                        Start Free
                    </a>
                    <a href="#features" class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-gray-700 bg-white border-2 border-gray-200 rounded-lg hover:border-gray-300 hover:shadow-md transition-all duration-200">
                        See How It Works
                    </a>
                </div>

                <!-- Trust Indicator -->
                <p class="mt-6 text-sm text-gray-500">
                    No credit card required · Start in minutes
                </p>
            </div>

            <!-- Right: Visual Element (40% on desktop) -->
            <div class="lg:col-span-2 hidden lg:block">
                <!-- Placeholder for future hero image/illustration -->
                <div class="relative aspect-square bg-gradient-to-br from-brand-purple/10 via-brand-blue/10 to-brand-pink/10 rounded-2xl"></div>
            </div>
        </div>
    </div>
</section>
