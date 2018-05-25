<?php


function autoloader($class)
{
	require __DIR__."/src/".str_replace("\\", "/", $class).".php";
}

spl_autoload_register("autoloader");