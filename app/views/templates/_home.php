<?php

use app\helpers\Asset;

use function app\helpers\e;

$currentPath = $viewInstance->request->uri;

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $_ENV['APP_NAME'] ?? 'system' ?></title>

    <?php require_once __DIR__ . '/refs/head.php' ?>

    <link rel="stylesheet" href="/assets/ui/css/styles.css">
    <link rel="stylesheet" href="/assets/ui/css/custom.css">

    <link rel="stylesheet" href="/assets/css/styles.css">

</head>

<body>

    <div class="container-fluid">
        <main id="app" class="main-wrapper">

            <nav class="sidebar">
                <div class="sidebar-header">
                    <div class="tmaz-logo sidebar-brand">
                        <img width="52px" src="/assets/src/imgs/TMaz.png" alt="LOGO TMAZ">
                    </div>
                    <div class="sidebar-toggler">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
               <!-- SIDEBAR -->
                <div class="sidebar-body">
                    <ul class="nav" id="sidebarNav">
                        <?php foreach ($modules as $categoryGroup): ?>

                            <!-- 1. Renderizar el Título de la Categoría -->
                            <li class="nav-item nav-category"><?= e(strtoupper($categoryGroup['category']->name)); ?></li>

                            <?php foreach ($categoryGroup['modules'] as $rootModule): ?>

                                <?php
                                // Preparamos una ID única para los elementos colapsables, basada en el ID del módulo
                                $collapseId = 'module-' . $rootModule->id;
                                ?>

                                <!-- 2. Comprobar si el módulo tiene hijos para decidir qué tipo de <li> renderizar -->
                                <li class="nav-item">

                                    <?php if (!empty($rootModule->children)): // <-- TIENE SUB-MENÚ 
                                    ?>

                                        <a class="nav-link" data-bs-toggle="collapse" href="#<?= e($collapseId); ?>" role="button" aria-expanded="false" aria-controls="<?= e($collapseId); ?>">
                                            <?php if (!empty($rootModule->icon)): ?>
                                                <i class="link-icon" data-lucide="<?= e($rootModule->icon); ?>"></i>
                                            <?php endif; ?>
                                            <span class="link-title"><?= e($rootModule->name); ?></span>
                                            <i class="link-arrow" data-lucide="chevron-down"></i>
                                        </a>
                                        <div class="collapse" data-bs-parent="#sidebarNav" id="<?= e($collapseId); ?>">
                                            <ul class="nav sub-menu">
                                                <?php foreach ($rootModule->children as $childModule): ?>
                                                    <button data-modulo="<?= e($childModule->url ?? '#'); ?>" class="nav-item">
                                                        <i class="link-icon" data-lucide="<?= e($childModule->icon); ?>"></i>
                                                        <p class="nav-link"><?= e($childModule->name); ?></p>
                                                    </button>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>

                                    <?php else: // <-- NO TIENE SUB-MENÚ (es un enlace simple) 
                                    ?>

                                        <a href="<?= e($rootModule->url ?? '#'); ?>" class="nav-link">
                                            <?php if (!empty($rootModule->icon)): ?>
                                                <i class="link-icon" data-lucide="<?= e($rootModule->icon); ?>"></i>
                                            <?php endif; ?>
                                            <span class="link-title"><?= e($rootModule->name); ?></span>
                                        </a>

                                    <?php endif; ?>

                                </li>
                            <?php endforeach; ?>

                        <?php endforeach; ?>
                    </ul>
                </div>
            </nav>

            <div class="page-wrapper">

                <nav class="navbar">
                    <div class="navbar-content">

                        <div class="system-title">
                            <?= $_ENV['APP_NAME'] ?? '' ?> <span><?= $_ENV['APP_ENV'] === 'QA' ? 'QA' : 'PRD' ?></span>
                        </div>

                        <!-- <form class="search-form">
                            <div class="logo-mini-wrapper">
                                <div class="tmaz-logo sidebar-brand">
                                    <img width="52px" src="/assets/src/imgs/TMaz.png" alt="LOGO TMAZ">
                                </div>
                                <img src="../../../assets/images/logo-mini-light.png" class="logo-mini logo-mini-light" alt="logo">
                                <img src="../../../assets/images/logo-mini-dark.png" class="logo-mini logo-mini-dark" alt="logo">
                            </div>
                        </form> -->

                        <ul class="navbar-nav">
                            <li class="theme-switcher-wrapper nav-item">
                                <input type="checkbox" value="" id="theme-switcher">
                                <label for="theme-switcher">
                                    <div class="box">
                                        <div class="ball"></div>
                                        <div class="icons">
                                            <i data-lucide="sun"></i>
                                            <i data-lucide="moon"></i>
                                        </div>
                                    </div>
                                </label>
                            </li>
                            <!-- <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle d-flex" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <img src="../../../assets/images/flags/us.svg" class="w-20px" title="us" alt="flag">
                                        <span class="ms-2 d-none d-md-inline-block">English</span>
                                    </a>
                                    <div class="dropdown-menu" aria-labelledby="languageDropdown">
                                        <a href="javascript:;" class="dropdown-item py-2 d-flex"><img src="../../../assets/images/flags/us.svg" class="w-20px" title="us" alt="us"> <span class="ms-2"> English </span></a>
                                        <a href="javascript:;" class="dropdown-item py-2 d-flex"><img src="../../../assets/images/flags/fr.svg" class="w-20px" title="fr" alt="fr"> <span class="ms-2"> French </span></a>
                                        <a href="javascript:;" class="dropdown-item py-2 d-flex"><img src="../../../assets/images/flags/de.svg" class="w-20px" title="de" alt="de"> <span class="ms-2"> German </span></a>
                                        <a href="javascript:;" class="dropdown-item py-2 d-flex"><img src="../../../assets/images/flags/pt.svg" class="w-20px" title="pt" alt="pt"> <span class="ms-2"> Portuguese </span></a>
                                        <a href="javascript:;" class="dropdown-item py-2 d-flex"><img src="../../../assets/images/flags/es.svg" class="w-20px" title="es" alt="es"> <span class="ms-2"> Spanish </span></a>
                                    </div>
                                </li> -->
                            <!-- <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="appsDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i data-lucide="layout-grid"></i>
                                </a>
                                <div class="dropdown-menu p-0" aria-labelledby="appsDropdown">
                                    <div class="px-3 py-2 d-flex align-items-center justify-content-between border-bottom">
                                        <p class="mb-0 fw-bold">Web Apps</p>
                                        <a href="javascript:;" class="text-secondary">Edit</a>
                                    </div>
                                    <div class="row g-0 p-1">
                                        <div class="col-3 text-center">
                                            <a href="#" class="dropdown-item d-flex flex-column align-items-center justify-content-center w-70px h-70px"><i data-lucide="message-square" class="icon-lg mb-1"></i>
                                                <p class="fs-12px">Chat</p>
                                            </a>
                                        </div>
                                        <div class="col-3 text-center">
                                            <a href="#" class="dropdown-item d-flex flex-column align-items-center justify-content-center w-70px h-70px"><i data-lucide="calendar" class="icon-lg mb-1"></i>
                                                <p class="fs-12px">Calendar</p>
                                            </a>
                                        </div>
                                        <div class="col-3 text-center">
                                            <a href="#" class="dropdown-item d-flex flex-column align-items-center justify-content-center w-70px h-70px"><i data-lucide="mail" class="icon-lg mb-1"></i>
                                                <p class="fs-12px">Email</p>
                                            </a>
                                        </div>
                                        <div class="col-3 text-center">
                                            <a href="#" class="dropdown-item d-flex flex-column align-items-center justify-content-center w-70px h-70px"><i data-lucide="instagram" class="icon-lg mb-1"></i>
                                                <p class="fs-12px">Profile</p>
                                            </a>
                                        </div>
                                    </div>
                                    <div class="px-3 py-2 d-flex align-items-center justify-content-center border-top">
                                        <a href="javascript:;">View all</a>
                                    </div>
                                </div>
                            </li> -->
                            <!-- <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="messageDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i data-lucide="mail"></i>
                                </a>
                                <div class="dropdown-menu p-0" aria-labelledby="messageDropdown">
                                    <div class="px-3 py-2 d-flex align-items-center justify-content-between border-bottom">
                                        <p>9 New Messages</p>
                                        <a href="javascript:;" class="text-secondary">Clear all</a>
                                    </div>
                                    <div class="p-1">
                                        <a href="javascript:;" class="dropdown-item d-flex align-items-center py-2">
                                            <div class="me-3">
                                                <img class="w-30px h-30px rounded-circle" src="https://placehold.co/30x30" alt="userr">
                                            </div>
                                            <div class="d-flex justify-content-between flex-grow-1">
                                                <div class="me-4">
                                                    <p>Leonardo Payne</p>
                                                    <p class="fs-12px text-secondary">Project status</p>
                                                </div>
                                                <p class="fs-12px text-secondary">2 min ago</p>
                                            </div>
                                        </a>
                                        <a href="javascript:;" class="dropdown-item d-flex align-items-center py-2">
                                            <div class="me-3">
                                                <img class="w-30px h-30px rounded-circle" src="https://placehold.co/30x30" alt="userr">
                                            </div>
                                            <div class="d-flex justify-content-between flex-grow-1">
                                                <div class="me-4">
                                                    <p>Carl Henson</p>
                                                    <p class="fs-12px text-secondary">Client meeting</p>
                                                </div>
                                                <p class="fs-12px text-secondary">30 min ago</p>
                                            </div>
                                        </a>
                                        <a href="javascript:;" class="dropdown-item d-flex align-items-center py-2">
                                            <div class="me-3">
                                                <img class="w-30px h-30px rounded-circle" src="https://placehold.co/30x30" alt="userr">
                                            </div>
                                            <div class="d-flex justify-content-between flex-grow-1">
                                                <div class="me-4">
                                                    <p>Jensen Combs</p>
                                                    <p class="fs-12px text-secondary">Project updates</p>
                                                </div>
                                                <p class="fs-12px text-secondary">1 hrs ago</p>
                                            </div>
                                        </a>
                                        <a href="javascript:;" class="dropdown-item d-flex align-items-center py-2">
                                            <div class="me-3">
                                                <img class="w-30px h-30px rounded-circle" src="https://placehold.co/30x30" alt="userr">
                                            </div>
                                            <div class="d-flex justify-content-between flex-grow-1">
                                                <div class="me-4">
                                                    <p>Amiah Burton</p>
                                                    <p class="fs-12px text-secondary">Project deatline</p>
                                                </div>
                                                <p class="fs-12px text-secondary">2 hrs ago</p>
                                            </div>
                                        </a>
                                        <a href="javascript:;" class="dropdown-item d-flex align-items-center py-2">
                                            <div class="me-3">
                                                <img class="w-30px h-30px rounded-circle" src="https://placehold.co/30x30" alt="userr">
                                            </div>
                                            <div class="d-flex justify-content-between flex-grow-1">
                                                <div class="me-4">
                                                    <p>Yaretzi Mayo</p>
                                                    <p class="fs-12px text-secondary">New record</p>
                                                </div>
                                                <p class="fs-12px text-secondary">5 hrs ago</p>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="px-3 py-2 d-flex align-items-center justify-content-center border-top">
                                        <a href="javascript:;">View all</a>
                                    </div>
                                </div>
                            </li> -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i data-lucide="bell"></i>
                                    <div class="indicator">
                                        <div class="circle"></div>
                                    </div>
                                </a>
                                <div class="dropdown-menu p-0" aria-labelledby="notificationDropdown">
                                    <div class="px-3 py-2 d-flex align-items-center justify-content-between border-bottom">
                                        <p>6 New Notifications</p>
                                        <a href="javascript:;" class="text-secondary">Clear all</a>
                                    </div>
                                    <div class="p-1">
                                        <a href="javascript:;" class="dropdown-item d-flex align-items-center py-2">
                                            <div class="w-30px h-30px d-flex align-items-center justify-content-center bg-primary rounded-circle me-3">
                                                <i class="icon-sm text-white" data-lucide="gift"></i>
                                            </div>
                                            <div class="flex-grow-1 me-2">
                                                <p>New Order Recieved</p>
                                                <p class="fs-12px text-secondary">30 min ago</p>
                                            </div>
                                        </a>
                                        <a href="javascript:;" class="dropdown-item d-flex align-items-center py-2">
                                            <div class="w-30px h-30px d-flex align-items-center justify-content-center bg-primary rounded-circle me-3">
                                                <i class="icon-sm text-white" data-lucide="alert-circle"></i>
                                            </div>
                                            <div class="flex-grow-1 me-2">
                                                <p>Server Limit Reached!</p>
                                                <p class="fs-12px text-secondary">1 hrs ago</p>
                                            </div>
                                        </a>
                                        <a href="javascript:;" class="dropdown-item d-flex align-items-center py-2">
                                            <div class="w-30px h-30px d-flex align-items-center justify-content-center bg-primary rounded-circle me-3">
                                                <img class="w-30px h-30px rounded-circle" src="https://placehold.co/30x30" alt="userr">
                                            </div>
                                            <div class="flex-grow-1 me-2">
                                                <p>New customer registered</p>
                                                <p class="fs-12px text-secondary">2 sec ago</p>
                                            </div>
                                        </a>
                                        <a href="javascript:;" class="dropdown-item d-flex align-items-center py-2">
                                            <div class="w-30px h-30px d-flex align-items-center justify-content-center bg-primary rounded-circle me-3">
                                                <i class="icon-sm text-white" data-lucide="layers"></i>
                                            </div>
                                            <div class="flex-grow-1 me-2">
                                                <p>Apps are ready for update</p>
                                                <p class="fs-12px text-secondary">5 hrs ago</p>
                                            </div>
                                        </a>
                                        <a href="javascript:;" class="dropdown-item d-flex align-items-center py-2">
                                            <div class="w-30px h-30px d-flex align-items-center justify-content-center bg-primary rounded-circle me-3">
                                                <i class="icon-sm text-white" data-lucide="download"></i>
                                            </div>
                                            <div class="flex-grow-1 me-2">
                                                <p>Download completed</p>
                                                <p class="fs-12px text-secondary">6 hrs ago</p>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="px-3 py-2 d-flex align-items-center justify-content-center border-top">
                                        <a href="javascript:;">View all</a>
                                    </div>
                                </div>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <img class="w-30px h-30px ms-1 rounded-circle" src="https://placehold.co/30x30" alt="profile">
                                </a>
                                <div class="dropdown-menu p-0" aria-labelledby="profileDropdown">
                                    <div class="d-flex flex-column align-items-center border-bottom px-5 py-3">
                                        <div class="mb-3">
                                            <img class="w-80px h-80px rounded-circle" src="https://placehold.co/80x80" alt="">
                                        </div>
                                        <div class="text-center">
                                            <p class="fs-16px fw-bolder"><?= e($session->username) ?></p>
                                            <p class="fs-12px text-secondary"><?= e($session->email) ?></p>
                                        </div>
                                    </div>
                                    <ul class="list-unstyled p-1">
                                        <li>
                                            <a href="#" class="dropdown-item py-2 text-body ms-0">
                                                <i class="me-2 icon-md" data-lucide="user"></i>
                                                <span>Perfil</span>
                                            </a>
                                        </li>
                                        <!-- <li>
                                            <a href="javascript:;" class="dropdown-item py-2 text-body ms-0">
                                                <i class="me-2 icon-md" data-lucide="edit"></i>
                                                <span>Edit Profile</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a href="javascript:;" class="dropdown-item py-2 text-body ms-0">
                                                <i class="me-2 icon-md" data-lucide="repeat"></i>
                                                <span>Switch User</span>
                                            </a>
                                        </li> -->
                                        <li>
                                            <a href="/logout" class="dropdown-item py-2 text-body ms-0">
                                                <i class="me-2 icon-md" data-lucide="log-out"></i>
                                                <span>Cerrar Sesión</span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        </ul>

                        <a href="#" class="sidebar-toggler">
                            <i data-lucide="menu"></i>
                        </a>

                    </div>
                </nav>

                <div class="page-content container-xxl">
                    <?php require $modulo # El módulo se renderiza aquí ?>
                </div>

            </div>
        </main>
    </div>

    <?php require_once __DIR__ . '/refs/scripts.php'; ?>
    <script type="module" src="/assets/js/main.js"></script>
</body>

</html>