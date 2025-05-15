<?php
function UseClasses($classe): void
{
    try {
        if (str_contains($classe, "src")) {
            $classe = str_replace('src', '', $classe);
            $classe = str_replace('\\', '/', $classe);

            $file = __DIR__ . '/../' . $classe . '.php';

            if (file_exists($file)) {
                require_once $file;
            } else {
                throw new Error("The class file '$file' is not found!");
            }
        }
    } catch (Error $e) {
        echo "Error: " . $e->getMessage();
    }
}

spl_autoload_register('UseClasses');
