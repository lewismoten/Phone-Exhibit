# Phone Exhibit

Converts audio files with a high pass filter 300-3000 Hz into 8kHz 16bit PCM mono WAV files to simulate telephone quality audio, and a format that can be used with an Asterisk PBX server.

* Copy config.sample.php to config.php and modify settings
* request/install ffmpeg on your server
* getID3: https://github.com/JamesHeinrich/getID3 and put in /lib/getid3
* get PHPMailer and put in /PHPMailer/src
