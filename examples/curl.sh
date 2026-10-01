#!/bin/sh
# Run: SYR_API_KEY='syp_...' sh examples/curl.sh currency USD
set -eu
: "${SYR_API_KEY:?Set SYR_API_KEY in your environment}"
base=${SYR_API_URL:-https://api.exchanger.sy}
base=${base%/}
command=${1:-}

usage() {
  printf '%s\n' 'Usage: sh examples/curl.sh COMMAND [arguments]' \
    '  rates | currencies | currency CODE | gold | gold-item KARAT' \
    '  energy | energy-item CODE' \
    '  history TYPE CODE FROM TO [PAGE] [PER_PAGE]' >&2
  exit 2
}

valid_code() {
  case "$1" in ''|*[!A-Za-z0-9_]*) usage ;; esac
}

case "$command" in
  rates)       [ "$#" -eq 1 ] || usage; path=/api/v1/rates ;;
  currencies)  [ "$#" -eq 1 ] || usage; path=/api/v1/currencies ;;
  currency)    [ "$#" -eq 2 ] || usage; valid_code "$2"; path="/api/v1/currencies/$2" ;;
  gold)        [ "$#" -eq 1 ] || usage; path=/api/v1/gold ;;
  gold-item)   [ "$#" -eq 2 ] || usage; valid_code "$2"; path="/api/v1/gold/$2" ;;
  energy)      [ "$#" -eq 1 ] || usage; path=/api/v1/energy ;;
  energy-item) [ "$#" -eq 2 ] || usage; valid_code "$2"; path="/api/v1/energy/$2" ;;
  history)
    [ "$#" -ge 5 ] && [ "$#" -le 7 ] || usage
    case "$2" in currency|gold) ;; *) usage ;; esac
    valid_code "$3"
    curl --silent --show-error --fail-with-body --include --get \
      -H "X-API-Key: $SYR_API_KEY" -H 'Accept: application/json' \
      --data-urlencode "type=$2" --data-urlencode "code=$3" \
      --data-urlencode "from=$4" --data-urlencode "to=$5" \
      --data-urlencode "page=${6:-1}" --data-urlencode "per_page=${7:-50}" \
      "$base/api/v1/history"
    exit $?
    ;;
  *) usage ;;
esac

curl --silent --show-error --fail-with-body --include \
  -H "X-API-Key: $SYR_API_KEY" -H 'Accept: application/json' \
  "$base$path"
