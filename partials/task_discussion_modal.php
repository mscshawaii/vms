<?php if (!isset($pdo)) require_once __DIR__ . '/../db_connect.php'; ?>

<style>

#taskDiscussionModal {
  z-index: 1065;
}

#taskDiscussionMessages {
  overflow-y: auto;
  min-height: 0;
}

#taskDiscussionMessages article {
  transition: background 0.15s ease, transform 0.05s ease;
}

#taskDiscussionMessages article:hover {
  background: rgba(0, 0, 0, 0.03);
  transform: translateY(-1px);
}

#taskDiscussionMessages .border {
  border-radius: 0.75rem !important;
}

#taskDiscussionForm {
  flex-shrink: 0;
}

</style>

<div class="modal fade" id="taskDiscussionModal" tabindex="-1" aria-labelledby="taskDiscussionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 1100px;">
    <div class="modal-content">
     <div class="modal-header bg-dark text-white">
  <h5 class="modal-title" id="taskDiscussionModalLabel">CAR Discussion</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body d-flex flex-column p-0" style="height: calc(100vh - 200px);">

  <div class="border-bottom p-3 bg-light">
    <div id="taskDiscussionTitle" class="fw-semibold">Task Discussion</div>
    <div id="taskDiscussionMeta" class="small text-muted">0 messages</div>
  </div>

  <div id="taskDiscussionMessages" class="flex-grow-1 p-3">
    <!-- messages go here -->
  </div>

  <div class="border-top p-2 bg-white">
    <form id="taskDiscussionForm" class="d-flex gap-2">
      <input type="hidden" id="taskDiscussionThreadId" value="">
      <input 
        type="text" 
        id="taskDiscussionInput" 
        class="form-control"
        placeholder="Add update, troubleshooting note, vendor response, or completion detail..."
      >
      <button class="btn btn-primary" type="submit">Send</button>
    </form>
  </div>

</div>

      <div class="progress" id="taskDiscussionLoader" style="height: 3px; display:none;">
        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const modalEl    = document.getElementById('taskDiscussionModal');
  const messagesEl = document.getElementById('taskDiscussionMessages');
  const formEl     = document.getElementById('taskDiscussionForm');
  const inputEl    = document.getElementById('taskDiscussionInput');
  const threadIdEl = document.getElementById('taskDiscussionThreadId');
  const loaderEl   = document.getElementById('taskDiscussionLoader');
  const titleEl    = document.getElementById('taskDiscussionTitle');
  const metaEl     = document.getElementById('taskDiscussionMeta');

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

    if (dateOnly.getTime() === today.getTime()) {
      return `Today ${timeStr}`;
    }
    if (dateOnly.getTime() === yesterday.getTime()) {
      return `Yesterday ${timeStr}`;
    }

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
  const bubbleClass = isMe
    ? 'bg-primary text-white'
    : 'bg-light';

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
      console.warn('Failed to mark thread read');
    }
  }

  function clearUnreadBadge(threadId) {
    document.querySelectorAll(`.open-task-discussion[data-thread-id="${threadId}"]`).forEach(btn => {
      btn.dataset.unreadCount = '0';
      btn.classList.remove('btn-outline-danger');
      btn.classList.add('btn-outline-dark');

      const unread = btn.querySelector('.discussion-unread');
      if (unread) unread.remove();
    });
  }

  async function loadMessages(threadId) {
    showLoader(true);
    try {
      const res = await fetch(`api/get_thread_messages.php?thread_id=${threadId}`);
      const data = await res.json();

      if (!data.ok) {
        messagesEl.innerHTML = `<div class="text-danger">${esc(data.error || 'Failed to load messages.')}</div>`;
        return;
      }

      const msgs = data.messages || [];
      if (!msgs.length) {
        messagesEl.innerHTML = `
          <div class="text-muted">
            No discussion yet.<br>
            <small>Use this thread for troubleshooting, updates, vendor coordination, and completion notes.</small>
          </div>
        `;
      } else {
        messagesEl.innerHTML = msgs.map(renderMessage).join('');
      }

      messagesEl.scrollTop = messagesEl.scrollHeight;
      metaEl.textContent = `${msgs.length} message${msgs.length === 1 ? '' : 's'}`;

      await markRead(threadId);
      clearUnreadBadge(threadId);

    } catch (e) {
      messagesEl.innerHTML = `<div class="text-danger">Failed to load messages.</div>`;
    } finally {
      showLoader(false);
    }
  }

  document.querySelectorAll('.open-task-discussion').forEach(btn => {
    btn.addEventListener('click', function() {
      const threadId = this.dataset.threadId;
      const taskTitle = this.dataset.taskTitle || 'CAR Discussion';
      const status = this.dataset.taskStatus || '';
      const assigned = this.dataset.taskAssigned || '';
      const due = this.dataset.taskDue || '';
      const count = this.dataset.messageCount || '0';

      threadIdEl.value = threadId;
      titleEl.textContent = taskTitle;
      metaEl.textContent = `${count} message${count === '1' ? '' : 's'}`;
      inputEl.value = '';
      inputEl.placeholder = 'Add update, troubleshooting note, vendor response, or completion detail...';
      titleEl.innerHTML = `
        <div>
            <div class="fw-bold">${esc(taskTitle)}</div>
            <div class="small text-muted">
            ${status ? `<span class="me-2">${esc(status)}</span>` : ''}
            ${assigned ? `<span class="me-2">Assigned: ${esc(assigned)}</span>` : ''}
            ${due ? `<span>Due: ${esc(due)}</span>` : ''}
            </div>
        </div>
        `;

      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
      loadMessages(threadId);
    });
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
      const emptyState = messagesEl.querySelector('.text-muted');
      if (emptyState && emptyState.textContent.includes('No discussion yet')) {
        messagesEl.innerHTML = '';
      }

      messagesEl.insertAdjacentHTML('beforeend', renderMessage(msg));
      inputEl.value = '';
      messagesEl.scrollTop = messagesEl.scrollHeight;

      document.querySelectorAll(`.open-task-discussion[data-thread-id="${threadId}"]`).forEach(btn => {
        const current = parseInt(btn.dataset.messageCount || '0', 10);
        const next = current + 1;
        btn.dataset.messageCount = String(next);

        const badge = btn.querySelector('.discussion-count');
        if (badge) badge.textContent = next;
      });

      await markRead(threadId);
      clearUnreadBadge(threadId);

      const countNow = parseInt(metaEl.textContent || '0', 10);
      if (!isNaN(countNow)) {
        metaEl.textContent = `${countNow + 1} message${(countNow + 1) === 1 ? '' : 's'}`;
      }

    } catch (e) {
      alert('Failed to post message.');
    }
  });
})();
</script>