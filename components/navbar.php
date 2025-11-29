<?php
$config = getConfig();
?>
<header class="bg-white shadow-sm">
    <nav class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <div class="flex items-center">
                <a href="#" class="inline-flex items-center gap-3">

                    <span class="font-semibold text-lg"><?php echo $config['web']['name']; ?></span>
                </a>
            </div>



            <div class="hidden md:flex md:items-center md:space-x-6">
                <a href="#" class="hover:text-blue-600">Home</a>
                <a href="#" class="hover:text-blue-600">Features</a>
                <a href="/type/20?games=50" class="hover:text-blue-600">Pricing</a>
                <a href="/about" class="hover:text-blue-600">about</a>
                <button class="ml-4 px-4 py-1.5 rounded-md bg-blue-600 text-white hover:bg-blue-700">Sign in</button>
            </div>



            <div class="md:hidden">
                <button id="nav-toggle" aria-controls="mobile-menu" aria-expanded="false"
                    class="p-2 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">

                    <svg id="icon-open" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg id="icon-close" class="h-6 w-6 hidden" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <div id="mobile-menu" class="md:hidden hidden bg-white border-t border-gray-100">
        <div class="px-4 pt-4 pb-6 space-y-3">
            <a href="#" class="block px-3 py-2 rounded-md hover:bg-gray-50">Home</a>
            <a href="#" class="block px-3 py-2 rounded-md hover:bg-gray-50">Features</a>
            <a href="#" class="block px-3 py-2 rounded-md hover:bg-gray-50">Pricing</a>
            <a href="#" class="block px-3 py-2 rounded-md hover:bg-gray-50">Contact</a>
            <div class="pt-2">
                <button class="w-full px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">Sign in</button>
            </div>
        </div>
    </div>
</header>
<script>

    const btn = document.getElementById('nav-toggle');
    const menu = document.getElementById('mobile-menu');
    const iconOpen = document.getElementById('icon-open');
    const iconClose = document.getElementById('icon-close');


    btn.addEventListener('click', () => {
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!expanded));



        iconOpen.classList.toggle('hidden');
        iconClose.classList.toggle('hidden');



        menu.classList.toggle('hidden');
    });



    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !menu.classList.contains('hidden')) {
            btn.setAttribute('aria-expanded', 'false');
            iconOpen.classList.remove('hidden');
            iconClose.classList.add('hidden');
            menu.classList.add('hidden');
        }
    });
</script>