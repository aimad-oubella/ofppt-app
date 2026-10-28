import { Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout.jsx';
import ProtectedRoute from './components/ProtectedRoute.jsx';
import Login from './pages/Login.jsx';
import Dashboard from './pages/Dashboard.jsx';
import Placeholder from './pages/Placeholder.jsx';

export default function App() {
  return (
    <Routes>
      <Route path="/connexion" element={<Login />} />
      <Route element={<ProtectedRoute><Layout /></ProtectedRoute>}>
        <Route index element={<Dashboard />} />
        <Route path="stagiaires" element={<Placeholder title="Stagiaires" phase="Phase 3" />} />
        <Route path="laureats" element={<Placeholder title="Lauréats" phase="Phase 3" />} />
        <Route path="importation" element={<Placeholder title="Importation Excel" phase="Phase 3" />} />
        <Route path="attestations" element={<Placeholder title="Attestations" phase="Phase 4" />} />
        <Route path="checklists" element={<Placeholder title="Check-lists diplômes" phase="Phase 4" />} />
        <Route path="emargement" element={<Placeholder title="Listes d'émargement" phase="Phase 4" />} />
        <Route path="parametres" element={<Placeholder title="Utilisateurs et paramètres" phase="Phase 5" />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
