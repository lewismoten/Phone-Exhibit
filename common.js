async function api(path, options = {}) {
  const {
    method = "GET",
    data = null,
    files = null,
    headers = {},
  } = options;

  let url = `/api/${String(path).replace(/^\/+/, "")}.php`;

  const fetchOptions = {
    method,
    headers: {
      Accept: "application/json",
      ...headers,
    },
  };

  if ((data || files) && method.toUpperCase() === 'GET') {
    const params = new URLSearchParams();

    if (data) {
      for (const [key, value] of Object.entries(data)) {
        params.append(key, value);
      }
    }

    url += (url.includes('?') ? '&' : '?') + params.toString();
  } else if (data || files) {
    const formData = new FormData();

    if (data) {
      for (const [key, value] of Object.entries(data)) {
        formData.append(key, value);
      }
    }

    if (files) {
      for (const [key, value] of Object.entries(files)) {
        if (value instanceof FileList || Array.isArray(value)) {
          for (const file of value) {
            formData.append(key, file);
          }
        } else {
          formData.append(key, value);
        }
      }
    }

    fetchOptions.body = formData;
  }

  try {
    const response = await fetch(url, fetchOptions);
    const text = await response.text();

    let result;
    try {
      result = text ? JSON.parse(text) : {};
    } catch {
      result = {
        success: false,
        error: "The server returned an invalid JSON response.",
        raw: text,
      };
    }

    if (!response.ok) {
      return {
        success: false,
        status: response.status,
        error: result.error || response.statusText || "Request failed.",
        result,
      };
    }

    return result;
  } catch (error) {
    return {
      success: false,
      error: error.message || "Network request failed.",
    };
  }
}

function api_upload(path, options = {}) {
  const {
    method = "POST",
    data = null,
    files = null,
    headers = {},
    onUploadProgress = null,
  } = options;

  const url = `/api/${String(path).replace(/^\/+/, "")}.php`;
  const formData = new FormData();

  if (data) {
    for (const [key, value] of Object.entries(data)) {
      formData.append(key, value);
    }
  }

  if (files) {
    for (const [key, value] of Object.entries(files)) {
      if (value instanceof FileList || Array.isArray(value)) {
        for (const file of value) {
          formData.append(key, file);
        }
      } else {
        formData.append(key, value);
      }
    }
  }

  return new Promise((resolve) => {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader("Accept", "application/json");

    for (const [key, value] of Object.entries(headers)) {
      xhr.setRequestHeader(key, value);
    }

    if (typeof onUploadProgress === "function" && xhr.upload) {
      xhr.upload.addEventListener("progress", onUploadProgress);
    }

    xhr.onload = () => {
      const text = xhr.responseText || "";
      let result;

      try {
        result = text ? JSON.parse(text) : {};
      } catch {
        result = {
          success: false,
          error: "The server returned an invalid JSON response.",
          raw: text,
        };
      }

      if (xhr.status < 200 || xhr.status >= 300) {
        resolve({
          success: false,
          status: xhr.status,
          error: result.error || xhr.statusText || "Request failed.",
          result,
        });
        return;
      }

      resolve(result);
    };

    xhr.onerror = () => {
      resolve({
        success: false,
        error: "Network request failed.",
      });
    };

    xhr.send(formData);
  });
}
function onReady(callback) {
  if (
    document.readyState === "complete" ||
    document.readyState === "interactive"
  ) {
    callback();
  } else {
    document.addEventListener("DOMContentLoaded", callback, {
      once: true,
    });
  }
}

const toc_item = (title, value = '-') => `
    <span class="bullet-item dot-leader">
        ${escape_html(title)}
    </span>
    <span>
        ${escape_html(value)}
    </span>
`;

const recent_contribution_item = (entry) => {
    const button = entry.playback_url
        ? compact_audio_player({
            id: `recent-${entry.id}`,
            playback_url: entry.playback_url,
            playback_mime_type: entry.playback_mime_type || 'audio/wav',
        })
        : '';

    return `
        <span class="bullet-item dot-leader recent-contribution-item">
            <span class="recent-contribution-label">
                <span class="recent-contribution-title">${escape_html(entry.title)}</span>
                ${button}
            </span>
        </span>
        <span>
            ${escape_html(time_ago(entry.date))}
        </span>
    `;
};

const show_recent_contributions = (id) => {
    const container = document.getElementById(id);
    if(container) {
        container.innerHTML = toc_item('Waiting for page to load');
    }

    onReady(async () => {
        const container = document.getElementById(id);
        if(!container) {
            console.error(`Element with id "${id}" not found.`);
            return;
        }
        container.innerHTML = toc_item('Fetching recent contributions');

        const result = await api("recent-contributions");

        if (!result.success) {
            container.innerHTML = toc_item('Unable to load contributions');
            return;
        }

        const entries = Array.isArray(result.entries)
            ? result.entries
            : [];

        if (!entries.length) {
            container.innerHTML = toc_item('No recent contributions');
            return;
        }

        container.innerHTML = entries.map(recent_contribution_item).join("");

    });
}

function time_ago(dateString) {

  const date = parse_api_date(dateString);
  if (!date) {
    return "just now";
  }

  const seconds = Math.floor((Date.now() - date.getTime()) / 1000);

  const units = [
    ["year", 31536000],
    ["month", 2592000],
    ["day", 86400],
    ["hour", 3600],
    ["minute", 60],
    ["second", 1],
  ];

  for (const [name, value] of units) {

    const amount = Math.floor(seconds / value);

    if (amount >= 1) {
      return `${amount} ${name}${amount !== 1 ? "s" : ""} ago`;
    }
  }

  return "just now";
}

function parse_api_date(dateString) {

  if (!dateString) {
    return null;
  }

  const mysqlStyle = String(dateString)
    .trim()
    .match(/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})$/);

  if (mysqlStyle) {
    const utcDate = new Date(`${mysqlStyle[1]}T${mysqlStyle[2]}Z`);
    if (!Number.isNaN(utcDate.getTime())) {
      return utcDate;
    }
  }

  const direct = new Date(dateString);
  if (Number.isNaN(direct.getTime())) {
    return null;
  }

  return direct;
}

function format_local_datetime(dateString) {

  const date = parse_api_date(dateString);
  if (!date) {
    return String(dateString || '');
  }

  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  }).format(date);
}

function show_toast(message, type = 'success', duration = 4000) {

  const text = String(message || '').trim();
  if (!text) {
    return;
  }

  let stack = document.getElementById('toast-stack');

  if (!stack) {
    stack = document.createElement('div');
    stack.id = 'toast-stack';
    stack.className = 'toast-stack';
    document.body.appendChild(stack);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.setAttribute('role', 'status');
  toast.setAttribute('aria-live', 'polite');
  toast.textContent = text;
  stack.appendChild(toast);

  requestAnimationFrame(() => {
    toast.classList.add('is-visible');
  });

  const dismiss = () => {
    toast.classList.remove('is-visible');
    window.setTimeout(() => {
      toast.remove();
      if (!stack.childElementCount) {
        stack.remove();
      }
    }, 220);
  };

  window.setTimeout(dismiss, duration);
}

function escape_html(value) {

  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

document.addEventListener('DOMContentLoaded', function () {
    const times = document.querySelectorAll('.local-datetime');

    times.forEach((el) => {
        const utcValue = el.dataset.utc;
        if (!utcValue) return;

        const date = new Date(utcValue);
        if (Number.isNaN(date.getTime())) return;

        const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        const formatted = new Intl.DateTimeFormat(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            timeZoneName: 'short'
        }).format(date);

        el.textContent = formatted;

        const label = el.parentElement.querySelector('.local-timezone-label');
        if (label && timeZone) {
            label.textContent = `(${timeZone}, your local time)`;
        }
    });
});

function format_phone_number(value) {

    if (!value) {
        return '—';
    }

    const digits = String(value).replace(/\D/g, '');

    if (digits.length === 7) {
        return `${digits.slice(0,3)}-${digits.slice(3)}`;
    }

    if (digits.length === 10) {
        return `(${digits.slice(0,3)}) ${digits.slice(3,6)}-${digits.slice(6)}`;
    }

    return value;
}

function compact_audio_player(row) {

    const id = `audio-${row.id}`;
    const tooltip = escape_html(
        row.file_tooltip
        || row.original_filename
        || row.title
        || ''
    );

    return `
        <div class="compact-player" title="${tooltip}">

            <svg class="progress-ring" viewBox="0 0 48 48">
                <circle
                    class="progress-ring-bg"
                    cx="24"
                    cy="24"
                    r="20"
                ></circle>

                <circle
                    id="${id}-progress"
                    class="progress-ring-fill"
                    cx="24"
                    cy="24"
                    r="20"
                    stroke-dasharray="126"
                    stroke-dashoffset="126"
                ></circle>
            </svg>

            <button
                type="button"
                class="compact-player-button"
                onclick="toggle_audio_player('${id}')"
                id="${id}-button"
                aria-label="Play audio"
                title="${tooltip}"
            >
                <span class="compact-player-icon" aria-hidden="true">
                    <svg viewBox="0 0 16 16" class="compact-player-icon-svg">
                        <polygon class="compact-player-play-shape" points="3.5,2 13.5,8 3.5,14"></polygon>
                        <g class="compact-player-pause-shape">
                            <rect x="3.5" y="2.25" width="3.25" height="11.5" rx="0.8"></rect>
                            <rect x="9.25" y="2.25" width="3.25" height="11.5" rx="0.8"></rect>
                        </g>
                    </svg>
                </span>
            </button>

            <audio
                id="${id}"
                preload="none"
                ontimeupdate="update_audio_progress('${id}')"
                onended="reset_audio_player('${id}')"
            >
                <source
                    src="${escape_html(row.playback_url)}"
                    type="${escape_html(row.playback_mime_type || 'audio/wav')}"
                >
            </audio>

        </div>
    `;
}
function toggle_audio_player(id) {

    const audio = document.getElementById(id);
    const button = document.getElementById(`${id}-button`);

    if (!audio || !button) {
        return;
    }

    if (audio.paused) {

        document
            .querySelectorAll('.compact-player audio')
            .forEach(other => {

                if (other !== audio) {
                    other.pause();
                    set_audio_button_state(other.id, false);
                }
            });

        audio.play();
        set_audio_button_state(id, true);

    } else {

        audio.pause();
        set_audio_button_state(id, false);
    }
}

function update_audio_progress(id) {

    const audio = document.getElementById(id);
    const progress = document.getElementById(`${id}-progress`);

    if (!audio || !progress || !audio.duration) {
        return;
    }

    const percent = audio.currentTime / audio.duration;
    const circumference = 126;

    progress.style.strokeDashoffset =
        circumference - (circumference * percent);
}

function reset_audio_player(id) {

    const progress = document.getElementById(`${id}-progress`);

    set_audio_button_state(id, false);

    if (progress) {
        progress.style.strokeDashoffset = 126;
    }
}

function set_audio_button_state(id, isPlaying) {

    const button = document.getElementById(`${id}-button`);
    if (!button) {
        return;
    }

    const icon = button.querySelector('.compact-player-icon');
    if (!icon) {
        return;
    }

    button.classList.toggle('is-playing', isPlaying);
    button.setAttribute('aria-label', isPlaying ? 'Pause audio' : 'Play audio');
}
