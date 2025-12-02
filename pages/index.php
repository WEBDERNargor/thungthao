<?php
use App\Controllers\UserController;
$config = getConfig();
$UserController = new UserController();
$users = $UserController->getUser();
$setHead(<<<HTML
<title> Home - {$config['web']['name']}</title>
HTML);
pre_r($users);
?>

<div class="bg-gray-50">
    <section class="relative overflow-hidden">
        <div class="mx-auto max-w-7xl px-6 lg:px-8 py-24">
            <div class="text-center">
                <span
                    class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-600 ring-1 ring-inset ring-blue-100">ยินดีต้อนรับ</span>
                <h1 class="mt-6 text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl">"Welcome to"
                    <?= htmlspecialchars($config['web']['name']) ?></h1>
                <p class="mt-6 text-lg leading-8 text-gray-600">PHP Start Code for Newbie</p>
                <div class="mt-10 flex items-center justify-center gap-x-4">
                    <a href="https://thungthao.online" target="_blank"
                        class="rounded-md bg-blue-600 px-6 py-3 text-white hover:bg-blue-700">Read Docs</a>

                </div>
            </div>
        </div>
    </section>


</div>