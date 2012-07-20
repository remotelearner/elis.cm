#!/bin/bash

if [ $# -ne 1 ]
then
    echo "Usage: runallunittests.sh {directory}"
    exit
fi

path=`pwd`

if [[ "$path" != */lib/phpunittestlib ]]
then
    echo "Please run this script from within lib/phpunittestlib"
    exit
fi

for dir in `find $1 -name "phpunit"`
do
    echo "Searching for PHPUnit files in directory $dir";
    for file in `find $dir -name "test*.php"`
    do
        echo "Found PHPUnit file $file";
        ./phpunit.php --include-path . --verbose "$file"
    done
    echo ""
done
