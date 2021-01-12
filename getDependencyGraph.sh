#!/bin/bash
echoerr() {
  if [[ -z "$1" ]]; then
    return 1
  fi

  echo "\033[7;49;31m $1\033[0m"
}

echoinf() {
  if [[ -z "$1" ]]; then
    return 1
  fi

  echo "\033[7;49;33m $1\033[0m"
}

echosuc() {
  if [[ -z "$1" ]]; then
    return 1
  fi

  echo "\033[7;49;32m $1\033[0m"
}

if [[ -z "$1" ]]; then
  echoerr "Please provide a Magento directory path."
  exit 1
fi

FILE_MAGENTO="$1/bin/magento"

if [ -f "$FILE_MAGENTO" ]; then
  echoinf "Starting .dot generation at: $(date +%Y\.%m\.%d) $(date +%H:%M:%S)"
  php index.php --magento-dir "$1" --module-vendor "$2" >"$(date +%Y-%m-%d)".dot
  echoinf "Completed .dot generation at: $(date +%Y\.%m\.%d) $(date +%H:%M:%S)"
  dot -Tpdf -o"$(date +%Y-%m-%d)".pdf -Tsvg -o"$(date +%Y-%m-%d)".svg "$(date +%Y-%m-%d)".dot
  echosuc "Done."
else
  echoerr "Provided directory '$1' is missing Magento 2 files"
fi
