<?php if (!isset($pdo)) require_once __DIR__ . '/../db_connect.php'; ?>

<style>
#vesselMessagesModalInner {
  z-index: 1065;
}

#vesselMessagesList {
  overflow-y: auto;
  min-height: 0;
}

#vesselMessagesList article {
  transition: background 0.15s ease, transform 0.05s ease;
}

#vesselMessagesList article:hover {
  background: rgba(0, 0, 0, 0.03);
  transform: translateY(-1px);
}

#vesselMessagesList .border {
  border-radius: 0.75rem !important;
}

#vesselMessagesForm {
  flex-shrink: 0;
}
</style>

<div id="vesselMessagesModalInner">
  <div class="d-flex flex-column" style="height: 70vh;">

    <div class="border-bottom p-3 bg-light">
      <div id="vesselMessagesTitle" class="fw-semibold">Vessel General</div>
      <div id="vesselMessagesMeta" class="small text-muted">0 messages</div>
    </div>

    <div id="vesselMessagesList" class="flex-grow-1 p-3">
      <div class="text-muted">Loading vessel discussion…</div>
    </div>

    <div class="border-top p-2 bg-white">
      <form id="vesselMessagesForm" class="d-flex gap-2">
        <input type="hidden" id="vesselMessagesThreadId" value="">
        <input
          type="text"
          id="vesselMessagesInput"
          class="form-control"
          placeholder="Post vessel update, maintenance note, scheduling item, or crew communication..."
        >
        <button class="btn btn-primary" type="submit">Send</button>
      </form>
    </div>

    <div class="progress" id="vesselMessagesLoader" style="height: 3px; display:none;">
      <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
    </div>

  </div>
</div>

<script>
(function(){
  const modalEl    = document.getElementById('vesselMessagesModal');
  const listEl     = document.getElementById('vesselMessagesList');
  const formEl     = document.getElementById('vesselMessagesForm');
  const inputEl    = document.getElementById('vesselMessagesInput');
  const threadIdEl = document.getElementById('vesselMessagesThreadId');
  const loaderEl   = document.getElementById('vesselMessagesLoader');
  const titleEl    = document.getElementById('vesselMessagesTitle');
  const metaEl     = document.getElementById('vesselMessagesMeta');

  function esc(str) {
    return String(str || '').replace(/[&<>"']/g, function(m) {
      return ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      })[m];
    });
  }

  function showLoader(on) {
    loaderEl.style.display = on ? 'block' : 'none';
  }

  function formatTimestamp(ts) {
    const d = new Date(ts.replace(' ', 'T'));
    if (isNaN(d.getTime())) return ts;

    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);

    const dateOnly = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const timeStr = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

    if (dateOnly.getTime() === today.getTime()) return `Today ${timeStr}`;
    if (dateOnly.getTime() === yesterday.getTime()) return `Yesterday ${timeStr}`;

    return d.toLocaleString([], {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit'
    });
  }

  function renderMessage(m) {
    const isMe = m.me;
    const alignClass = isMe ? 'text-end' : 'text-start';
    const bubbleClass = isMe ? 'bg-primary text-white' : 'bg-light';

    return `
      <article class="mb-3 ${alignClass}">
        <div class="small text-muted mb-1">
          ${isMe ? '' : `<strong>${esc(m.author)}</strong> • `}
          ${esc(formatTimestamp(m.created_at))}
        </div>
        <div class="d-inline-block border rounded px-3 py-2 ${bubbleClass}" style="max-width: 75%;">
          ${esc(m.body).replace(/\n/g, '<br>')}
        </div>
      </article>
    `;
  }

  async function markRead(threadId) {
    try {
      await fetch('api/mark_thread_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ thread_id: threadId })
      });
    } catch (e) {
      console.warn('Failed to mark vessel thread read');
    }
  }

  async function loadMessages(threadId) {
    showLoader(true);
    try {
      const res = await fetch(`api/get_thread_messages.php?thread_id=${threadId}`);
      const data = await res.json();

      if (!data.ok) {
        listEl.innerHTML = `<div class="text-danger">${esc(data.error || 'Failed to load messages.')}</div>`;
        return;
      }

      const msgs = data.messages || [];
      if (!msgs.length) {
        listEl.innerHTML = `
          <div class="text-muted">
            No vessel discussion yet.<br>
            <small>Use this channel for operations, maintenance planning, crew coordination, and vessel-wide updates.</small>
          </div>
        `;
      } else {
        listEl.innerHTML = msgs.map(renderMessage).join('');
      }

      listEl.scrollTo({ top: listEl.scrollHeight, behavior: 'smooth' });
      metaEl.textContent = `${msgs.length} message${msgs.length === 1 ? '' : 's'}`;

      await markRead(threadId);
    } catch (e) {
      listEl.innerHTML = `<div class="text-danger">Failed to load messages.</div>`;
    } finally {
      showLoader(false);
    }
  }

  modalEl.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    if (!btn) return;

    const threadId = btn.getAttribute('data-thread-id');
    const title = btn.getAttribute('data-thread-title') || 'Vessel General';

    threadIdEl.value = threadId;
    titleEl.textContent = title;
    metaEl.textContent = 'Loading...';
    inputEl.value = '';
    listEl.innerHTML = `<div class="text-muted">Loading vessel discussion…</div>`;

    setTimeout(() => inputEl.focus(), 150);
    loadMessages(threadId);
  });

  inputEl.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      formEl.requestSubmit();
    }
  });

  formEl.addEventListener('submit', async function(e) {
    e.preventDefault();

    const threadId = parseInt(threadIdEl.value || '0', 10);
    const body = inputEl.value.trim();
    if (!threadId || !body) return;

    try {
      const res = await fetch('api/post_thread_message.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ thread_id: threadId, body })
      });

      const data = await res.json();
      if (!data.ok) {
        alert(data.error || 'Failed to post message.');
        return;
      }

      const msg = data.message;
      const emptyState = listEl.querySelector('.text-muted');
      if (emptyState && emptyState.textContent.includes('No vessel discussion yet')) {
        listEl.innerHTML = '';
      }

      listEl.insertAdjacentHTML('beforeend', renderMessage(msg));
      inputEl.value = '';
      listEl.scrollTo({ top: listEl.scrollHeight, behavior: 'smooth' });

      const countNow = parseInt(metaEl.textContent || '0', 10);
      if (!isNaN(countNow)) {
        metaEl.textContent = `${countNow + 1} message${(countNow + 1) === 1 ? '' : 's'}`;
      }

      await markRead(threadId);
    } catch (e) {
      alert('Failed to post message.');
    }
  });
})();
</script>