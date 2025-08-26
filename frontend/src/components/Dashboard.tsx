import { useAuth } from '../contexts/AuthContext';
import { useNavigate } from 'react-router-dom';

export default function Dashboard() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  return (
    <div className="min-h-screen bg-gray-100 p-6">
      <div className="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-8">
        <div className="flex justify-between items-center mb-8">
          <h1 className="text-2xl font-bold">Dashboard</h1>
          <button
            onClick={handleLogout}
            className="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded"
          >
            Logout
          </button>
        </div>

        <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
          <p className="text-blue-700">
            Welcome back, <span className="font-semibold">{user?.name || 'User'}</span>!
          </p>
        </div>

        <div className="mb-6">
          <h2 className="text-xl font-semibold mb-4">Your Profile</h2>
          <div className="bg-gray-50 p-4 rounded">
            <p className="mb-2">
              <span className="font-semibold">Name:</span> {user?.name}
            </p>
            <p>
              <span className="font-semibold">Email:</span> {user?.email}
            </p>
          </div>
        </div>

        <div>
          <h2 className="text-xl font-semibold mb-4">Account Security</h2>
          <p className="text-gray-600 mb-4">
            You're currently logged in with a secure session. Your authentication token is stored
            locally in your browser and will be used for secure API requests.
          </p>
          <div className="flex space-x-4">
            <button className="border border-gray-300 bg-white text-gray-700 font-semibold py-2 px-4 rounded hover:bg-gray-50">
              Change Password
            </button>
            <button className="border border-gray-300 bg-white text-gray-700 font-semibold py-2 px-4 rounded hover:bg-gray-50">
              Update Profile
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}