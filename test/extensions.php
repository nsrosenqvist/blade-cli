<?php

$compiler->extend('no', function ($expression) {
    return "<?php echo $expression; ?>";
});
