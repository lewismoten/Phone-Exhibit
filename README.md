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

Master worker, calls all other cron jobs to process audio files.
```
* * * * * /usr/bin/php /home/USER/public_html/phone/cron/cron.php >> /home/USER/logs/cron-$(date +\%Y-\%m-\%d).log 2>&1
```

Delete old logs
```
0 3 * * * find /path/to/logs -name "cron-*.log" -mtime +90 -delete
```
