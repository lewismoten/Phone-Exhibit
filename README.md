# Phone Exhibit

Converts audio files with a high pass filter 300-3000 Hz into 8kHz 16bit PCM mono WAV files to simulate telephone quality audio, and a format that can be used with an Asterisk PBX server.

* Copy config.sample.php to config.php and modify settings
* request/install ffmpeg on your server
* getID3: https://github.com/JamesHeinrich/getID3 and put in /lib/getid3
* Install minimodem and use which miinimodem to determine where it is installed

AI transcriptions and Minimodem is used for TTY Teletype machines.

## Cron Jobs

Master worker, calls all other cron jobs to process audio files.
```
* * * * * /usr/bin/php /home/USER/public_html/phone/cron/cron.php >> /home/USER/logs/cron-$(date +\%Y-\%m-\%d).log 2>&1
```

Delete old logs
```
0 3 * * * find /path/to/logs -name "cron-*.log" -mtime +90 -delete
```
