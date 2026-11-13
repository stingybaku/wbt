<?php

if ($_SERVER['REQUEST_URI'] === '/redirect') {
    header('Location: http://127.0.0.1/blocked');
    exit;
}
echo str_repeat('x', 8192);
