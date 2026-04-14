import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import { getLastSeen, isAfter, setLastSeen } from '../utils/notificationStorage';

const DEFAULT_POLL_MS = 60000;

export const useNotifications = (options = {}) => {
  const { poll = true, pollMs = DEFAULT_POLL_MS } = options;
  const summary = ref({ role: '', tabs: {} });
  const loading = ref(false);
  const error = ref('');
  let timer = null;

  const fetchSummary = async () => {
    loading.value = true;
    error.value = '';
    try {
      const { data } = await axios.get('/api/notifications/summary');
      summary.value = data || { role: '', tabs: {} };
    } catch (e) {
      error.value = e?.response?.data?.message || 'Failed to load notifications.';
    } finally {
      loading.value = false;
    }
  };

  const hasDot = (tabKey) => {
    const tab = summary.value?.tabs?.[tabKey];
    if (!tab) return false;
    if (tab.needs_action) return true;
    if (!tab.latest_at) return false;
    const lastSeen = getLastSeen(tabKey);
    return isAfter(tab.latest_at, lastSeen);
  };

  const markSeen = (tabKey, latestAt = null) => {
    if (!tabKey) return;
    setLastSeen(tabKey, latestAt || new Date().toISOString());
  };

  const isRowNew = (tabKey, updatedAt) => {
    if (!updatedAt) return false;
    const lastSeen = getLastSeen(tabKey);
    return isAfter(updatedAt, lastSeen);
  };

  onMounted(() => {
    fetchSummary();
    if (poll) {
      timer = setInterval(fetchSummary, pollMs);
    }
  });

  onUnmounted(() => {
    if (timer) clearInterval(timer);
  });

  return {
    summary,
    loading,
    error,
    fetchSummary,
    hasDot,
    markSeen,
    isRowNew,
  };
};
