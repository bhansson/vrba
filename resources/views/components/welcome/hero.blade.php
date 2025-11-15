<section class="relative pt-24 sm:pt-32 pb-20 px-6 lg:px-8 overflow-hidden bg-gradient-to-br from-white via-purple-50/30 to-blue-50/30">
    <!-- Animated Background Elements -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute top-20 left-10 w-72 h-72 bg-brand-purple/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-20 right-10 w-96 h-96 bg-brand-blue/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-80 h-80 bg-brand-pink/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
    </div>

    <div class="relative mx-auto max-w-7xl">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
            <!-- Left: Content -->
            <div class="text-center lg:text-left">
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/80 backdrop-blur-sm rounded-full border border-gray-200 shadow-sm mb-8">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-purple opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-brand-purple"></span>
                    </span>
                    <span class="text-sm font-medium text-gray-700">AI-Powered Product Management</span>
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-bold text-gray-900 tracking-tight leading-tight">
                    Scale your catalog with
                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-brand-purple via-brand-blue to-brand-pink">
                        AI magic
                    </span>
                </h1>

                <p class="mt-6 text-lg sm:text-xl text-gray-600 leading-relaxed max-w-2xl mx-auto lg:mx-0">
                    Transform product data into compelling marketing assets in seconds. Import catalogs, generate copy, and create stunning visuals—all from one intelligent platform built for e-commerce teams.
                </p>

                <!-- CTA Buttons -->
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                    <a href="{{ route('register') }}" class="group relative inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-brand-gradient rounded-xl hover:shadow-2xl transition-all duration-300 overflow-hidden">
                        <span class="relative z-10 flex items-center gap-2">
                            Start Free Trial
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </span>
                    </a>
                    <a href="#how-it-works" class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-gray-700 bg-white/80 backdrop-blur-sm border-2 border-gray-200 rounded-xl hover:border-gray-300 hover:shadow-lg transition-all duration-200">
                        Watch Demo
                        <svg class="ml-2 w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </a>
                </div>

                <!-- Trust Indicators -->
                <div class="mt-10 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-6 text-sm text-gray-600">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>No credit card required</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Setup in 5 minutes</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Cancel anytime</span>
                    </div>
                </div>
            </div>

            <!-- Right: Visual Element -->
            <div class="relative hidden lg:block">
                <!-- Main Card with Dashboard Preview -->
                <div class="relative">
                    <!-- Floating Stats Card -->
                    <div class="absolute -top-4 -left-4 bg-white rounded-2xl shadow-xl p-6 border border-gray-100 z-10 animate-float">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-green-600 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Products Generated</p>
                                <p class="text-2xl font-bold text-gray-900">12,547</p>
                            </div>
                        </div>
                    </div>

                    <!-- Floating AI Badge -->
                    <div class="absolute -bottom-6 -right-6 bg-white rounded-2xl shadow-xl px-6 py-4 border border-gray-100 z-10 animate-float" style="animation-delay: 0.5s;">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="w-3 h-3 bg-green-500 rounded-full animate-ping absolute"></div>
                                <div class="w-3 h-3 bg-green-500 rounded-full relative"></div>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">AI Processing</p>
                                <p class="text-xs text-gray-600">3 images generating...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Main Dashboard Mockup -->
                    <div class="relative bg-white rounded-3xl shadow-2xl border border-gray-200 p-8 backdrop-blur-xl">
                        <!-- Mock Browser Bar -->
                        <div class="flex items-center gap-2 mb-6 pb-4 border-b border-gray-200">
                            <div class="w-3 h-3 bg-red-400 rounded-full"></div>
                            <div class="w-3 h-3 bg-yellow-400 rounded-full"></div>
                            <div class="w-3 h-3 bg-green-400 rounded-full"></div>
                        </div>

                        <!-- Mock Product Cards Grid -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-4 p-4 bg-gradient-to-r from-brand-purple/10 to-brand-blue/10 rounded-xl">
                                <div class="w-16 h-16 bg-brand-gradient rounded-lg"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-4 bg-gray-300 rounded w-3/4"></div>
                                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 p-4 bg-gradient-to-r from-brand-blue/10 to-brand-pink/10 rounded-xl">
                                <div class="w-16 h-16 bg-brand-gradient rounded-lg"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-4 bg-gray-300 rounded w-2/3"></div>
                                    <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 p-4 bg-gradient-to-r from-brand-pink/10 to-brand-purple/10 rounded-xl">
                                <div class="w-16 h-16 bg-brand-gradient rounded-lg"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-4 bg-gray-300 rounded w-5/6"></div>
                                    <div class="h-3 bg-gray-200 rounded w-2/5"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="mt-20 grid grid-cols-2 md:grid-cols-4 gap-8 p-8 bg-white/50 backdrop-blur-sm rounded-2xl border border-gray-200">
            <div class="text-center">
                <p class="text-3xl md:text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-brand-purple to-brand-blue">500K+</p>
                <p class="mt-2 text-sm text-gray-600">Products Managed</p>
            </div>
            <div class="text-center">
                <p class="text-3xl md:text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-brand-blue to-brand-pink">2M+</p>
                <p class="mt-2 text-sm text-gray-600">AI Generations</p>
            </div>
            <div class="text-center">
                <p class="text-3xl md:text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-brand-pink to-brand-purple">95%</p>
                <p class="mt-2 text-sm text-gray-600">Time Saved</p>
            </div>
            <div class="text-center">
                <p class="text-3xl md:text-4xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-brand-purple to-brand-blue">24/7</p>
                <p class="mt-2 text-sm text-gray-600">AI Available</p>
            </div>
        </div>
    </div>
</section>

<!-- Floating Animation Keyframes -->
<style>
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
    }
    .animate-float {
        animation: float 3s ease-in-out infinite;
    }
</style>
