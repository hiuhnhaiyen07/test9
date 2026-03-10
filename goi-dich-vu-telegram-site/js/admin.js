(function () {
  const toast = document.getElementById('toast');
  const loginCard = document.getElementById('adminLoginCard');
  const dashboard = document.getElementById('adminDashboard');
  const logoutBtn = document.getElementById('logoutBtn');
  const loginForm = document.getElementById('adminLoginForm');
  const loginError = document.getElementById('adminLoginError');
  const tabs = Array.from(document.querySelectorAll('.admin-nav-link'));
  const tabPanels = Array.from(document.querySelectorAll('.admin-tab'));

  const state = {
    csrfToken: null,
    currentPage: 1,
    totalPages: 1,
    selectedOrder: null,
  };

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(showToast._timer);
    showToast._timer = setTimeout(() => toast.classList.remove('show'), 2200);
  }

  function safeJson(response) {
    return response.text().then((text) => {
      try {
        return JSON.parse(text);
      } catch (error) {
        throw new Error(text || 'Phản hồi máy chủ không hợp lệ.');
      }
    });
  }

  async function api(url, options) {
    const response = await fetch(url, { credentials: 'same-origin', ...(options || {}) });
    const data = await safeJson(response);
    if (!response.ok || data.ok === false) {
      const message = data && data.message ? data.message : 'Có lỗi xảy ra.';
      throw new Error(message);
    }
    return data;
  }

  function formatMoney(value) {
    return new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + 'đ';
  }

  function formatDate(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('vi-VN', {
      hour: '2-digit',
      minute: '2-digit',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(date);
  }

  function statusBadge(status) {
    const normalized = (status || 'pending').toLowerCase();
    return '<span class="status-pill status-' + normalized + '">' + normalized + '</span>';
  }

  function setAuthenticated(authenticated) {
    loginCard.hidden = authenticated;
    dashboard.hidden = !authenticated;
    logoutBtn.hidden = !authenticated;
  }

  function activateTab(tabName) {
    tabs.forEach((button) => button.classList.toggle('active', button.dataset.tab === tabName));
    tabPanels.forEach((panel) => panel.classList.toggle('active', panel.id === 'tab-' + tabName));
  }

  function renderStats(stats) {
    const container = document.getElementById('statsGrid');
    if (!container) return;
    const items = [
      { label: 'Tổng đơn', value: stats.total || 0 },
      { label: 'Chờ thanh toán', value: stats.pending || 0 },
      { label: 'Đã xác nhận', value: (stats.paid || 0) + (stats.processing || 0) },
      { label: 'Doanh thu đã xác nhận', value: formatMoney(stats.revenue_paid || 0) },
    ];
    container.innerHTML = items.map((item) => (
      '<article class="card stat-card">' +
        '<p>' + item.label + '</p>' +
        '<strong>' + item.value + '</strong>' +
      '</article>'
    )).join('');
  }

  function renderRecentOrders(items) {
    const tbody = document.getElementById('recentOrdersBody');
    if (!tbody) return;
    if (!items || !items.length) {
      tbody.innerHTML = '<tr class="empty-row"><td colspan="4">Chưa có đơn hàng.</td></tr>';
      return;
    }
    tbody.innerHTML = items.map((item) => (
      '<tr>' +
        '<td><strong>' + item.order_id + '</strong><br><small>' + formatDate(item.created_at) + '</small></td>' +
        '<td>' + (item.email || '-') + '</td>' +
        '<td>' + statusBadge(item.status) + '</td>' +
        '<td>' + formatMoney(item.amount) + '</td>' +
      '</tr>'
    )).join('');
  }

  function renderOrders(items, pagination) {
    const tbody = document.getElementById('ordersTableBody');
    const label = document.getElementById('paginationLabel');
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    if (!tbody) return;

    state.totalPages = pagination.total_pages || 1;
    state.currentPage = pagination.page || 1;
    label.textContent = 'Trang ' + state.currentPage + ' / ' + state.totalPages + ' • ' + (pagination.total_items || 0) + ' đơn';
    prevBtn.disabled = state.currentPage <= 1;
    nextBtn.disabled = state.currentPage >= state.totalPages;

    if (!items || !items.length) {
      tbody.innerHTML = '<tr class="empty-row"><td colspan="6">Không có đơn phù hợp với bộ lọc.</td></tr>';
      return;
    }

    tbody.innerHTML = items.map((item) => (
      '<tr>' +
        '<td><strong>' + item.order_id + '</strong><br><small>' + (item.transaction_id || '-') + '</small></td>' +
        '<td>' + (item.email || '-') + '<br><small>' + (item.note || '-') + '</small></td>' +
        '<td>' + formatDate(item.created_at) + '</td>' +
        '<td>' + statusBadge(item.status) + '</td>' +
        '<td>' + formatMoney(item.amount) + '</td>' +
        '<td>' +
          '<div class="action-buttons">' +
            '<button type="button" data-order-select="' + item.order_id + '">Chọn</button>' +
          '</div>' +
        '</td>' +
      '</tr>'
    )).join('');

    Array.from(document.querySelectorAll('[data-order-select]')).forEach((button) => {
      button.addEventListener('click', function () {
        selectOrder(items.find((item) => item.order_id === button.getAttribute('data-order-select')) || null);
      });
    });
  }

  function selectOrder(order) {
    if (!order) return;
    state.selectedOrder = order;
    document.getElementById('selectedOrderId').value = order.order_id || '';
    document.getElementById('selectedOrderStatus').value = order.status || 'pending';
    document.getElementById('selectedOrderNote').value = order.admin_note || '';
    document.getElementById('orderActionMessage').textContent = 'Đang chọn ' + order.order_id + ' • ' + (order.email || '-');
  }

  async function loadOverview() {
    const data = await api('api/admin_stats.php');
    renderStats(data.stats || {});
    renderRecentOrders(data.recent_orders || []);
  }

  async function loadOrders(page) {
    const query = document.getElementById('orderSearch').value.trim();
    const status = document.getElementById('orderStatusFilter').value;
    const params = new URLSearchParams({ page: String(page || 1), page_size: '10', q: query, status });
    const data = await api('api/admin_orders.php?' + params.toString());
    renderOrders(data.items || [], data.pagination || { page: 1, total_pages: 1, total_items: 0 });
  }

  async function loadSettings() {
    const data = await api('api/admin_settings.php');
    const settings = data.settings || {};
    const mapping = {
      siteName: settings.site_name,
      siteTagline: settings.site_tagline,
      price: settings.price,
      planName: settings.plan_name,
      bankName: settings.bank_name,
      bankAccountName: settings.bank_account_name,
      bankAccountNumber: settings.bank_account_number,
      supportLabel: settings.support_label,
      supportLink: settings.support_link,
      heroBadge: settings.hero_badge,
      ctaSubtitle: settings.cta_subtitle,
      telegramToken: settings.telegram_bot_token,
      telegramChatId: settings.telegram_chat_id,
      webhookSecret: settings.webhook_secret,
    };
    Object.keys(mapping).forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = mapping[id] == null ? '' : mapping[id];
    });
    const preview = document.getElementById('settingsQrPreview');
    if (preview && settings.qr_image_path) {
      preview.src = settings.qr_image_path;
    }
  }

  async function refreshAll() {
    await Promise.all([loadOverview(), loadOrders(state.currentPage || 1), loadSettings()]);
  }

  async function checkSession() {
    const data = await api('api/admin_me.php');
    state.csrfToken = data.csrf_token || null;
    setAuthenticated(!!data.authenticated);
    return !!data.authenticated;
  }

  function bindTabs() {
    tabs.forEach((button) => {
      button.addEventListener('click', function () {
        activateTab(button.dataset.tab);
      });
    });
  }

  function bindLogin() {
    if (!loginForm) return;
    loginForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      loginError.hidden = true;
      const passwordEl = document.getElementById('adminPassword');
      const button = loginForm.querySelector('button[type="submit"]');
      const original = button.textContent;
      button.disabled = true;
      button.textContent = 'Đang đăng nhập...';
      try {
        const data = await api('api/admin_login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ password: passwordEl.value }),
        });
        state.csrfToken = data.csrf_token || null;
        setAuthenticated(true);
        await refreshAll();
        showToast('Đăng nhập thành công');
      } catch (error) {
        loginError.textContent = error.message || 'Không đăng nhập được.';
        loginError.hidden = false;
      } finally {
        button.disabled = false;
        button.textContent = original;
      }
    });
  }

  function bindToolbar() {
    document.getElementById('refreshOverviewBtn').addEventListener('click', async function () {
      try {
        await loadOverview();
        showToast('Đã làm mới tổng quan');
      } catch (error) {
        showToast(error.message || 'Không làm mới được');
      }
    });

    document.getElementById('refreshOrdersBtn').addEventListener('click', async function () {
      try {
        await loadOrders(state.currentPage || 1);
        showToast('Đã làm mới lịch sử đơn');
      } catch (error) {
        showToast(error.message || 'Không làm mới được');
      }
    });

    document.getElementById('orderFilterForm').addEventListener('submit', async function (event) {
      event.preventDefault();
      try {
        await loadOrders(1);
      } catch (error) {
        showToast(error.message || 'Không lọc được');
      }
    });

    document.getElementById('prevPageBtn').addEventListener('click', function () {
      if (state.currentPage > 1) {
        loadOrders(state.currentPage - 1).catch((error) => showToast(error.message || 'Không chuyển trang được'));
      }
    });

    document.getElementById('nextPageBtn').addEventListener('click', function () {
      if (state.currentPage < state.totalPages) {
        loadOrders(state.currentPage + 1).catch((error) => showToast(error.message || 'Không chuyển trang được'));
      }
    });
  }

  function bindOrderActions() {
    const form = document.getElementById('orderActionForm');
    const messageEl = document.getElementById('orderActionMessage');
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const orderId = document.getElementById('selectedOrderId').value.trim();
      if (!orderId) {
        showToast('Hãy chọn một đơn trước');
        return;
      }
      try {
        const data = await api('api/admin_order_action.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': state.csrfToken,
          },
          body: JSON.stringify({
            order_id: orderId,
            status: document.getElementById('selectedOrderStatus').value,
            admin_note: document.getElementById('selectedOrderNote').value,
          }),
        });
        messageEl.textContent = data.message || 'Đã cập nhật đơn.';
        await Promise.all([loadOverview(), loadOrders(state.currentPage || 1)]);
        showToast('Đã lưu trạng thái đơn');
      } catch (error) {
        messageEl.textContent = error.message || 'Không cập nhật được đơn.';
      }
    });

    document.getElementById('resendTelegramBtn').addEventListener('click', async function () {
      const orderId = document.getElementById('selectedOrderId').value.trim();
      if (!orderId) {
        showToast('Hãy chọn một đơn trước');
        return;
      }
      try {
        const data = await api('api/admin_order_action.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': state.csrfToken,
          },
          body: JSON.stringify({ order_id: orderId, action: 'resend_telegram' }),
        });
        document.getElementById('orderActionMessage').textContent = data.message || 'Đã gửi lại Telegram.';
        showToast('Đã gửi lại Telegram');
      } catch (error) {
        document.getElementById('orderActionMessage').textContent = error.message || 'Không gửi lại được Telegram.';
      }
    });
  }

  function bindSettings() {
    const form = document.getElementById('settingsForm');
    const preview = document.getElementById('settingsQrPreview');
    const qrInput = document.getElementById('qrImage');
    const messageEl = document.getElementById('settingsMessage');

    qrInput.addEventListener('change', function () {
      const [file] = qrInput.files || [];
      if (!file) return;
      preview.src = URL.createObjectURL(file);
    });

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const formData = new FormData(form);
      try {
        const response = await fetch('api/admin_settings.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'X-CSRF-Token': state.csrfToken,
          },
          body: formData,
        });
        const data = await safeJson(response);
        if (!response.ok || data.ok === false) {
          throw new Error(data.message || 'Không lưu được cấu hình.');
        }
        messageEl.textContent = data.message || 'Đã cập nhật cấu hình.';
        await loadSettings();
        showToast('Đã lưu cấu hình');
        form.querySelector('#newAdminPassword').value = '';
      } catch (error) {
        messageEl.textContent = error.message || 'Không lưu được cấu hình.';
      }
    });
  }

  function bindQuickTools() {
    document.getElementById('testTelegramBtn').addEventListener('click', async function () {
      try {
        await api('api/send_telegram.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': state.csrfToken,
          },
          body: JSON.stringify({ message: '✅ Test Telegram từ admin dashboard' }),
        });
        showToast('Đã gửi test Telegram');
      } catch (error) {
        showToast(error.message || 'Không gửi được test Telegram');
      }
    });

    logoutBtn.addEventListener('click', async function () {
      try {
        await api('api/admin_logout.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': state.csrfToken,
          },
          body: JSON.stringify({}),
        });
        setAuthenticated(false);
        state.csrfToken = null;
        showToast('Đã đăng xuất');
      } catch (error) {
        showToast(error.message || 'Không đăng xuất được');
      }
    });
  }

  async function boot() {
    bindTabs();
    bindLogin();
    bindToolbar();
    bindOrderActions();
    bindSettings();
    bindQuickTools();
    activateTab('overview');

    try {
      const isAuthenticated = await checkSession();
      if (isAuthenticated) {
        await refreshAll();
      }
    } catch (error) {
      console.error(error);
      setAuthenticated(false);
    }
  }

  boot();
})();
