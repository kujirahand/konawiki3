#!/usr/bin/env bash

if [[ $(which git) != "" ]]
then
    composer install
    sed -i -e 's/defC("KONA3_GIT_ENABLED", false);/defC("KONA3_GIT_ENABLED", true);/g' index.inc.php
fi
