# Phone Exhibit

Converts audio files with a high pass filter 300-3000 Hz into 8kHz 16bit PCM mono WAV files to simulate telephone quality audio, and a format that can be used with an Asterisk PBX server.

* Copy config.sample.php to config.php and modify settings
* request/install ffmpeg on your server
* getID3: https://github.com/JamesHeinrich/getID3 and put in /lib/getid3
* Install minimodem and use which miinimodem to determine where it is installed

AI transcriptions and Minimodem is used for TTY Teletype machines.

## FTP Publishing

Do not store FTP credentials inside this project, even in ignored files. If credentials are saved anywhere under the project folder, they can be read by local tools working in the repo.

This repo includes a deploy script at `scripts/deploy-ftp.php` that reads secrets from a JSON file in your home directory and deploy settings from a JSON file in the project.

1. Copy `ftp.regaldragondanceparty.com.json.example` to `~/ftp.regaldragondanceparty.com.json`.
2. Fill in your FTP host, port, username, password, passive mode, SSL choice, and timeout in the home-directory copy.
3. Copy `ftp.deploy.json.example` to `ftp.deploy.json`.
4. Set `remotePath` in `ftp.deploy.json` to the destination folder on the server.
5. Optionally change `manifestPath` in `ftp.deploy.json` if you want the remote manifest stored somewhere other than `.ftp-deploy-manifest.json`.
6. Run `php scripts/deploy-ftp.php`.

Deploy behavior:
* If a remote manifest already exists, the script downloads it first.
* Only files whose hash or size changed are uploaded.
* Files that failed in a prior run are listed separately in the manifest and retried after all other changed files.
* During upload, the remote manifest is checkpointed about every 10 seconds.
* If an upload fails, the script still tries to save a partial manifest for files already uploaded successfully and records the failed file separately for deferred retry next time.
* The manifest also records `startedAt`, `lastCheckpointAt`, `completedAt`, and an `errors` list for the current deploy attempt.
* Files matching `.gitignore` are excluded by default, except content under `lib/` remains deployable.
* The remote manifest is added or updated after the deploy finishes.

You can also pass a custom config path:
`php scripts/deploy-ftp.php ~/some-other-config.json`

Server details:
* Host: `ftp.regaldragondanceparty.com`
* Protocol: `ftp`
* Port: `21`

Recommended upload excludes:
* Anything matched by `.gitignore`, except `lib/`
* `README.md`
* `.gitignore`
* `schema/`
* `*.example`
* `ftp.deploy.json`
* `scripts/`

Database schema files now live in `schema/` and are intended to be run in filename order:
* `001-...sql`
* `002-...sql`
* `003-...sql`
* ...
* `011-schema-script-deployments.sql`

The `schema_script_deployments` table created by `011-...sql` seeds records for the existing split schema files and can be used to record whether later schema deployments were started, succeeded, or failed.

To apply pending schema files:
```bash
php scripts/deploy-schema.php
```

The schema deploy script now reads from `config.php`:
* `BASE_URL`
  Example: `https://phone.lewismoten.com`
* `SCHEMA_DEPLOY_API_TOKEN`
  Used as the bearer token when posting schema files to:
  `BASE_URL . '/api/deploy-schema.php'`

If the tracker table does not exist yet, the script posts only `011-schema-script-deployments.sql` first, then uses `schema_script_deployments` from the remote server to determine the next unapplied file.

## Cron Jobs

Master worker, calls all other cron jobs to process audio files. Use a PHP binary
whose SAPI is `cli`; some hosting providers map `/usr/bin/php` to CGI/FastCGI,
which cannot run this worker.

Find and verify the CLI binary before adding the cron entry:

```bash
command -v php
php -r 'echo PHP_SAPI, PHP_EOL;'
```

The second command must print `cli`. If it does not, try the provider's CLI PHP
path and verify it the same way. For example, this server uses:

```bash
/usr/local/bin/php -r 'echo PHP_SAPI, PHP_EOL;'
```

Master worker cron entry (replace the PHP and project paths for your server):
```
* * * * * /usr/local/bin/php /home/USER/public_html/phone/cron/cron.php >> /home/USER/logs/cron-$(date +\%Y-\%m-\%d).log 2>&1
```

Delete old logs
```
0 3 * * * find /path/to/logs -name "cron-*.log" -mtime +90 -delete
```

## Install phone files on the Asterisk PBX

From the Admin Audio Phone List, download both **Phone config** and **WAV
archive**. The downloads are `phone-exhibit.conf` and a timestamped
`phone-exhibit-wavs-*.zip` file. The archive contains the `phone-exhibit/` and
`phone-exhibit-tty/` sound directories expected by the generated dialplan.

From the computer where the downloads were saved, copy both files to the PBX:

```bash
cd ~/Downloads
scp phone-exhibit.conf phone-exhibit-wavs-*.zip user@host.local:/tmp/
```

Connect to the PBX, install the dialplan config, extract the WAV files into
Asterisk's sounds directory, and reload the dialplan:

```bash
ssh user@host.local
sudo install -o root -g root -m 0644 /tmp/phone-exhibit.conf /etc/asterisk/phone-exhibit.conf
sudo unzip -o /tmp/phone-exhibit-wavs-*.zip -d /var/lib/asterisk/sounds/
sudo asterisk -rx 'dialplan reload'
```

There is no requirement for an `asterisk` system user. Root-owned WAV files
are fine when they are readable by the account running Asterisk, as the files
from this archive normally are. If playback reports a permissions error, find
the service account with `ps -eo user,comm | grep '[a]sterisk'` and grant it
read access rather than assuming an `asterisk:asterisk` owner.

`/etc/asterisk/extensions.conf` must include the generated config, usually with this line:

```ini
#include phone-exhibit.conf
```

Only add that include once. The extraction overwrites WAVs included in the new
archive; WAVs for removed assignments are left in place but are not reachable
unless a dialplan entry still references them.
