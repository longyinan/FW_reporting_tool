import axios from 'axios';
import { startLoading, stopLoading } from './loading';

const http = axios.create({
  headers: { 'X-Requested-With': 'XMLHttpRequest' },
  timeout: 15000,
});

http.interceptors.request.use((config) => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (token) config.headers['X-CSRF-TOKEN'] = token;
  if (config?.showLoading !== false) startLoading();
  return config;
});

http.interceptors.response.use(
  (res) => {
    if (res.config?.showLoading !== false) stopLoading();
    return res;
  },
  (err) => {
    if (err.config?.showLoading !== false) stopLoading();
    if (err.response?.status === 401) {
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

export default http;
