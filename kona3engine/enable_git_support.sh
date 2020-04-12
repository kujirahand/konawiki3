#!/usr/bin/env bash

if [[ $(which git) != "" ]]
then
    composer install
    sed -i -e 's/defC("KONA3_GIT_ENABLED", false);/defC("KONA3_GIT_ENABLED", true);/g' index.inc.php
else
    echo "Please install git and re-run this script."
fi
