<?php
function secure_random_str($length = 32) {
    return bin2hex(random_bytes($length / 2));
}
