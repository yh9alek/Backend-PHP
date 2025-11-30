<?php

use function app\helpers\e;
use function app\helpers\route;

?>

<div id="error" style="width: 600px; padding-top: 200px; margin: 0 auto;">
    <div style="display: flex; justify-content: flex-start; align-items: flex-end; gap: 40px; margin-bottom: 50px;">
        <img src="/assets/src/imgs/TMaz_error.png" style="width: 200px;">
    </div>
    <?php if($_SERVER['PATH_INFO']): ?>
        <p style="color: #9AA0A6; font-size: 24px; font-family: 'Segoe UI'; font-weight: 500;"><?= e($title) ?></p>
        <div style="margin-bottom: 50px;">
            <p style="color:#9AA0A6; font-size: 15px; font-family: 'Segoe UI';"><?= e($message) ?></p>
            <p style="color:#9AA0A6; font-size: 15px; font-family: 'Segoe UI';">ERROR: <span class="err"><?= e($errorCode) ?></span></p>
        </div>
        <div style="display: flex; justify-content: flex-start; align-items: center;">
            <a href="#" id="btnRegresar" onclick="window.history.back();">Regresar</a>
        </div>
    <?php endif ?>
</div>

<style>

    #btnRegresar {
        text-decoration: none;
        text-align: center;
        border: 0;
        border-radius: 20px;
        box-sizing: border-box;
        color: #202124;
        background-color: #8AB4F8;
        font-family: Arial, Helvetica, sans-serif;
        cursor: pointer;
        float: right;
        font-size: 13.125px;
        margin: 0;
        padding: 6px 16px;
        transition: box-shadow 150ms cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
    }

    .path {
        font-weight: bold;
        color: #e6844cff;
    }

    .err {
        font-weight: bold;
        color: #ED6C47;
    }

    @media (max-width: 690px) {
        #error {
            width: 100% !important;
            padding: 200px 20px;
        }
    }

    @media (max-width: 420px) {
        #btnRegresar {
            padding: 12px 24px;
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 90%;
        }
    }
</style>