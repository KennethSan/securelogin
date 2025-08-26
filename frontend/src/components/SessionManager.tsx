import { useState, useEffect } from 'react';
import api from '../services/api';

interface Session {
  id: string;
  ip_address: string;
  user_agent: string;
  last_active: string;
  is_current: boolean;
}

export default function SessionManager() {
  const [sessions, setSessions] = useState<Session[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchSessions();
  }, []);

  const fetchSessions = async () => {
    try {
      const response = await api.get('/user/sessions');
      setSessions(response.data);
    } catch (error) {
      console.error('Failed to fetch sessions', error);
    } finally {
      setLoading(false);
    }
  };

  const terminateSession = async (id: string) => {
    try {
      await api.delete(`/user/sessions/${id}`);
      setSessions(sessions.filter(session => session.id !== id));
    } catch (error) {
      console.error('Failed to terminate session', error);
    }
  };

  if (loading) {
    return <div>Loading sessions...</div>;
  }

  return (
    <div className="mt-6">
      <h3 className="text-lg font-medium">Active Sessions</h3>
      <div className="mt-4 space-y-4">
        {sessions.length === 0 ? (
          <p className="text-gray-500">No active sessions found.</p>
        ) : (
          sessions.map(session => (
            <div key={session.id} className="flex justify-between items-center p-4 border rounded">
              <div>
                <p className="font-medium">
                  {session.is_current ? 'Current Session' : 'Session'} • {session.ip_address}
                </p>
                <p className="text-sm text-gray-500">
                  {session.user_agent} • Last active {session.last_active}
                </p>
              </div>
              {!session.is_current && (
                <button
                  onClick={() => terminateSession(session.id)}
                  className="px-3 py-1 bg-red-100 text-red-800 rounded hover:bg-red-200"
                >
                  Logout
                </button>
              )}
            </div>
          ))
        )}
      </div>
    </div>
  );
}