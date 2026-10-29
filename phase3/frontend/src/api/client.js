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

/** Retire les paramètres vides avant l'envoi. */
export function cleanParams(params) {
  return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined));
}

/** Télécharge un fichier protégé (Excel...) avec la session de l'utilisateur. */
export async function downloadFile(url, params, filename) {
  const res = await api.get(url, { params: cleanParams(params || {}), responseType: 'blob' });
  const blobUrl = URL.createObjectURL(res.data);
  const a = document.createElement('a');
  a.href = blobUrl;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(blobUrl);
}

export default api;
