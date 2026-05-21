<?php
header("Content-Type: text/plain; charset=utf-8");
header("Access-Control-Allow-Origin: *");
if (file_exists(__DIR__ . "/skill.md")) {
    readfile(__DIR__ . "/skill.md");
} else {
    echo "# MoltDeals Skill File Not Found";
}