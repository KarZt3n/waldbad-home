#!/bin/sh

set -eu

secret_file='/srv/webapps/waldbad-home/secrets/stage_htpasswd'

usage() {
    printf 'Aufruf: %s add <benutzername> | remove <benutzername> | list\n' "$0" >&2
}

validate_username() {
    case "$1" in
        ''|*[!A-Za-z0-9._-]*)
            printf 'Der Benutzername darf nur Buchstaben, Zahlen, Punkt, Unterstrich und Bindestrich enthalten.\n' >&2
            exit 2
            ;;
    esac
}

replace_user_entry() {
    username="$1"
    entry="$2"
    temporary_file="$(mktemp /srv/webapps/waldbad-home/secrets/.stage-htpasswd.XXXXXX)"
    chmod 0600 "$temporary_file"

    if [ -f "$secret_file" ]; then
        grep -v "^${username}:" "$secret_file" > "$temporary_file" || true
    fi
    if [ -n "$entry" ]; then
        printf '%s\n' "$entry" >> "$temporary_file"
    fi
    mv -- "$temporary_file" "$secret_file"
}

action="${1:-}"
username="${2:-}"

case "$action" in
    add)
        [ "$#" -eq 2 ] || { usage; exit 2; }
        validate_username "$username"

        restore_echo() {
            stty echo 2>/dev/null || true
        }
        trap restore_echo EXIT HUP INT TERM

        printf 'Passwort für %s: ' "$username"
        stty -echo
        IFS= read -r password
        stty echo
        printf '\nPasswort wiederholen: '
        stty -echo
        IFS= read -r password_confirmation
        stty echo
        printf '\n'
        trap - EXIT HUP INT TERM

        if [ "$password" != "$password_confirmation" ]; then
            unset password password_confirmation
            printf 'Die Passwörter stimmen nicht überein.\n' >&2
            exit 1
        fi
        if [ "${#password}" -lt 12 ]; then
            unset password password_confirmation
            printf 'Das Passwort muss mindestens 12 Zeichen lang sein.\n' >&2
            exit 1
        fi

        entry="$(docker run --rm httpd:2.4-alpine htpasswd -nbB "$username" "$password")"
        unset password password_confirmation
        replace_user_entry "$username" "$entry"
        printf 'Stage-Benutzer %s wurde gespeichert.\n' "$username"
        ;;
    remove)
        [ "$#" -eq 2 ] || { usage; exit 2; }
        validate_username "$username"
        printf 'Stage-Benutzer %s wirklich entfernen? [y/N] ' "$username"
        IFS= read -r confirmation
        case "$confirmation" in
            y|Y|yes|YES|ja|JA) replace_user_entry "$username" '' ;;
            *) printf 'Abgebrochen.\n'; exit 0 ;;
        esac
        printf 'Stage-Benutzer %s wurde entfernt.\n' "$username"
        ;;
    list)
        [ "$#" -eq 1 ] || { usage; exit 2; }
        if [ -f "$secret_file" ]; then
            cut -d: -f1 "$secret_file"
        fi
        ;;
    *)
        usage
        exit 2
        ;;
esac
