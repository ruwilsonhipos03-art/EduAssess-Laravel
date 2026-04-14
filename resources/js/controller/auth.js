import axios from 'axios';

export const setAuthToken = (token) => {
    if (token) {
        // Save to persistent storage
        localStorage.setItem('auth_token', token);
        // Attach to all future axios requests
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    } else {
        localStorage.removeItem('auth_token');
        delete axios.defaults.headers.common['Authorization'];
    }
};

export const getAuthToken = () => localStorage.getItem('auth_token');

export const logout = () => {
    setAuthToken(null);
    window.location.href = '/login';
};
