#!/bin/bash

ENV=""
TAG_NAME=""
PUSH_TAG=0
SHOW_HELP=0
OFFLINE=0

# Source: https://stackoverflow.com/a/46793269/5155484 and https://stackoverflow.com/a/28466267/5155484
optspec="hpo-:e:n:"
while getopts "$optspec" OPTCHAR; do

    if [ "$OPTCHAR" = "-" ]; then   # long option: reformulate OPT and OPTARG
        OPTCHAR="${OPTARG%%=*}"       # extract long option name
        OPTARG="${OPTARG#$OPT}"   # extract long option argument (may be empty)
        OPTARG="${OPTARG#=}"      # if long option argument, remove assigning `=`
    fi
    OPTARG=${OPTARG#*=}

    # echo "OPTARG:  ${OPTARG[*]}"
    # echo "OPTIND:  ${OPTIND[*]}"
    # echo "OPTCHAR:  ${OPTCHAR}"
    case "${OPTCHAR}" in
        h|help)
            SHOW_HELP=1
            ;;
        o|offline)
            OFFLINE=1
            ;;
        p|push-tag)
            PUSH_TAG=1
            ;;
        n|tag-name)
            TAG_NAME="${OPTARG}"
            ;;
        e|env)
            ENV="${OPTARG}"
            ;;
        *)
            if [ "$OPTERR" != 1 ] || [ "${optspec:0:1}" = ":" ]; then
                echo "Non-option argument: '-${OPTARG}'" >&2
            fi
            ;;
    esac
done

shift $((OPTIND-1)) # remove parsed options and args from $@ list

if [ ${SHOW_HELP} -gt 0 ]; then
    echo 'Usage:'
    echo 'release.sh --env=production -p'
    echo 'release.sh --env=staging    -p'
    echo 'POSIX options:		long options:'
    echo '  -h                      --help          To have some help'
    echo '  -e                      --env=          To specify the env (staging/production)'
    echo '  -n                      --tag-name=     To specify the tag name'
    echo '  -p                      --push-tag      To push the tag'
    echo '  -o                      --offline       Do not fetch tags'
    exit 0;
fi

if [ -z $ENV ]; then
    echo "please enter a --env"
    exit 1
fi

if [ ${OFFLINE} -eq 0 ]; then
    echo "Fetching latest tags..."
    git fetch --prune origin "+refs/tags/*:refs/tags/*"
fi

echo "Get last release"
ENV_TAGS=$(git tag -l HEAD "$ENV/*" --sort='-*taggerdate')
DAY_TAGS=$(echo -e "${ENV_TAGS}" | grep -F "$(date +'%Y-%m-%d')")
# shellcheck disable=SC2046
LAST_TAG=$(git describe --tags `git rev-list --tags --max-count=1`)

# No tag name defined so use the latest tag
if [ -z "${TAG_NAME}" ]; then
    LAST_RELEASE=$(echo -e "${DAY_TAGS}" | head -n1)
else
    # Tag name defined so use the last tag before last one (offset 1)
    LAST_RELEASE=$(echo -e "${DAY_TAGS}" | sed -n 2p)
fi

if [ -z "$LAST_RELEASE" ]; then
    echo "None today, using first one"
    LAST_RELEASE=$(echo "$ENV/$(date +'%Y-%m-%d')-0");# will be +1 below
else
    if [ "$LAST_TAG" = "$LAST_RELEASE" ]; then
        echo "Found: $LAST_RELEASE"
    else
        LAST_RELEASE=$(echo "${LAST_TAG}")
        echo "Made: $LAST_RELEASE"
    fi
fi

echo "Version bump..."
if [ -z "${TAG_NAME}" ]; then
    # Cut on last - and bump last number
    VERSION_NAME=$(echo "${LAST_RELEASE}" | awk -F"-" '{print substr($0, 0, length($0) - length($NF)) $NF + 1 }')
else
    VERSION_NAME="$TAG_NAME"
fi

echo "New version: $VERSION_NAME"
if [ -z "${TAG_NAME}" ]; then
    git tag --message="release: $VERSION_NAME
user: $USER" $VERSION_NAME
    if [ ${PUSH_TAG} -eq 1 ]; then
        git push origin $VERSION_NAME
    fi
else
    echo "Using tag: ${TAG_NAME}"
    git tag --message="release: $TAG_NAME
user: $USER" $TAG_NAME
    if [ ${PUSH_TAG} -eq 1 ]; then
        git push origin $TAG_NAME
    fi
fi

TAG_WORKS=$?
exit $TAG_WORKS
