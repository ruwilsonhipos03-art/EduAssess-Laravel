const STORAGE_KEY = 'examchecker.notification_state.v1';

export const getUserStorage = () => {
  if (localStorage.getItem('auth_token')) {
    return localStorage;
  }
  return sessionStorage;
};

export const getUserRole = () => {
  const storage = getUserStorage();
  const raw = storage.getItem('user_data');
  if (!raw) return '';
  try {
    const parsed = JSON.parse(raw);
    return String(parsed?.role || '').trim();
  } catch (e) {
    return '';
  }
};

const loadState = () => {
  const storage = getUserStorage();
  const raw = storage.getItem(STORAGE_KEY);
  if (!raw) return {};
  try {
    return JSON.parse(raw) || {};
  } catch (e) {
    return {};
  }
};

const saveState = (state) => {
  const storage = getUserStorage();
  storage.setItem(STORAGE_KEY, JSON.stringify(state));
};

const roleBucket = (state) => {
  const role = getUserRole() || 'unknown';
  if (!state[role]) {
    state[role] = {};
  }
  return { state, role };
};

export const getLastSeen = (tabKey) => {
  const state = loadState();
  const role = getUserRole() || 'unknown';
  return state?.[role]?.[tabKey]?.last_seen || null;
};

export const setLastSeen = (tabKey, timestamp) => {
  if (!tabKey) return;
  const { state, role } = roleBucket(loadState());
  if (!state[role][tabKey]) state[role][tabKey] = {};
  state[role][tabKey].last_seen = timestamp || new Date().toISOString();
  saveState(state);
};

export const isAfter = (a, b) => {
  if (!a) return false;
  const aTime = Date.parse(a);
  const bTime = b ? Date.parse(b) : 0;
  if (Number.isNaN(aTime)) return false;
  if (Number.isNaN(bTime)) return true;
  return aTime > bTime;
};
