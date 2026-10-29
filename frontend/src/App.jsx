import { Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout.jsx';
import ProtectedRoute from './components/ProtectedRoute.jsx';
import Login from './pages/Login.jsx';
import Dashboard from './pages/Dashboard.jsx';
import Placeholder from './pages/Placeholder.jsx';
import Trainees from './pages/Trainees.jsx';
import TraineeForm from './pages/TraineeForm.jsx';
import TraineeDetail from './pages/TraineeDetail.jsx';
import Laureats from './pages/Laureats.jsx';
import References from './pages/References.jsx';
import ImportHome from './pages/import/ImportHome.jsx';
import ImportWizard from './pages/import/ImportWizard.jsx';

const WRITERS = ['administrateur', 'gestionnaire'];

export default function App() {
  return (
    <Routes>
      <Route path="/connexion" element={<Login />} />
      <Route element={<ProtectedRoute><Layout /></ProtectedRoute>}>
        <Route index element={<Dashboard />} />
        <Route path="stagiaires" element={<Trainees />} />
        <Route path="stagiaires/nouveau" element={<ProtectedRoute roles={WRITERS}><TraineeForm /></ProtectedRoute>} />
        <Route path="stagiaires/:id" element={<TraineeDetail />} />
        <Route path="stagiaires/:id/modifier" element={<ProtectedRoute roles={WRITERS}><TraineeForm /></ProtectedRoute>} />
        <Route path="laureats" element={<Laureats />} />
        <Route path="importation" element={<ProtectedRoute roles={WRITERS}><ImportHome /></ProtectedRoute>} />
        <Route path="importation/:id" element={<ProtectedRoute roles={WRITERS}><ImportWizard /></ProtectedRoute>} />
        <Route path="referentiels" element={<ProtectedRoute roles={['administrateur']}><References /></ProtectedRoute>} />
        <Route path="attestations" element={<Placeholder title="Attestations" phase="Phase 4" />} />
        <Route path="checklists" element={<Placeholder title="Check-lists diplômes" phase="Phase 4" />} />
        <Route path="emargement" element={<Placeholder title="Listes d'émargement" phase="Phase 4" />} />
        <Route path="parametres" element={<Placeholder title="Utilisateurs et paramètres" phase="Phase 5" />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
