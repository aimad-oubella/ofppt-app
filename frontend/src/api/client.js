import axios from 'axios';

const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

export async function fetchCsrfCookie() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

export function errorMessage(error) {
  const data = error?.response?.data;
  if (data?.errors) return Object.values(data.errors).flat().join(' ');
  if (data?.message) return data.message;
  if (!error?.response) return "Impossible de joindre le serveur. Vérifiez que l'API Laravel est démarrée.";
  return 'Une erreur est survenue.';
}

export default api;
