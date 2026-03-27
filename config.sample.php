<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'phone_exhibits';
const DB_USER = 'your_db_user';
const DB_PASS = 'your_db_password';

const APP_NAME = 'Phone Exhibit';
const BASE_URL = 'https://your-domain.example';
const PASSWORD_RESET_EXPIRY_MINUTES = 60;

const MAIL_FROM_ADDRESS = 'noreply@your-domain.example';
const MAIL_FROM_NAME = 'Phone Exhibit';

const UPLOAD_BASE_DIR = __DIR__ . '/uploads/audio';
const UPLOAD_BASE_URL = '/uploads/audio';
const ONE_KB = 1024;
const ONE_MB = ONE_KB * 1024;
const MAX_AUDIO_UPLOAD_BYTES = 100 * ONE_MB;
