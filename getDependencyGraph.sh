#!/bin/bash
echoerr() {
  if [[ -z "$1" ]]; then
    return 1
  fi

  echo -e "\033[37;1;41m $1\033[0m"
}

echoinf() {
  if [[ -z "$1" ]]; then
    return 1
  fi

  echo -e "\033[30;1;43m $1\033[0m"
}

echosuc() {
  if [[ -z "$1" ]]; then
    return 1
  fi

  echo -e "\033[30;1;42m $1\033[0m"
}

if [[ -z "$1" ]]; then
  echoerr "Please provide a Magento directory path."
  exit 1
fi

FILE_MAGENTO="$1/bin/magento"

if [ -f "$FILE_MAGENTO" ]; then
  php index.php --magento-dir "$1" --module-vendor "$2" >"$(date +%Y-%m-%d)".dot
  dot -Tpdf -o"$(date +%Y-%m-%d)".pdf -Tsvg -o"$(date +%Y-%m-%d)".svg "$(date +%Y-%m-%d)".dot
else
  echoerr "Provided directory is missing Magento 2 files"
fi
