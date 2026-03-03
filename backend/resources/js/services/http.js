import axios from 'axios';

const http = axios.create({
  headers: { 'X-Requested-With': 'XMLHttpRequest' },
  timeout: 15000,
});

http.interceptors.request.use((config) => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (token) config.headers['X-CSRF-TOKEN'] = token;
  return config;
});

http.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

export default http;