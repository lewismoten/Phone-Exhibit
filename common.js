async function api(path, options = {}) {
  const {
    method = "GET",
    data = null,
    files = null,
    headers = {},
  } = options;

  const url = `/api/${String(path).replace(/^\/+/, "")}.php`;

  const fetchOptions = {
    method,
    headers: {
      Accept: "application/json",
      ...headers,
    },
  };

  if (data || files) {
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

        container.innerHTML = entries.map(
            entry => toc_item(entry.title, time_ago(entry.date))
        ).join("");

    });
}

function time_ago(dateString) {

  const date = new Date(dateString);
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
