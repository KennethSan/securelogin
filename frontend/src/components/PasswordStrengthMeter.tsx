import { useEffect, useState } from 'react';

interface PasswordStrengthMeterProps {
  password: string;
}

export default function PasswordStrengthMeter({ password }: PasswordStrengthMeterProps) {
  const [strength, setStrength] = useState(0);
  const [feedback, setFeedback] = useState('');

  useEffect(() => {
    calculateStrength(password);
  }, [password]);

  const calculateStrength = (password: string) => {
    if (!password) {
      setStrength(0);
      setFeedback('');
      return;
    }

    let score = 0;
    
    // Length check
    if (password.length >= 8) score += 1;
    if (password.length >= 12) score += 1;
    
    // Complexity checks
    if (/[A-Z]/.test(password)) score += 1;
    if (/[a-z]/.test(password)) score += 1;
    if (/[0-9]/.test(password)) score += 1;
    if (/[^A-Za-z0-9]/.test(password)) score += 1;
    
    // Set feedback based on score
    switch(Math.min(score, 5)) {
      case 0:
        setFeedback('Very weak');
        break;
      case 1:
      case 2:
        setFeedback('Weak');
        break;
      case 3:
      case 4:
        setFeedback('Good');
        break;
      case 5:
        setFeedback('Strong');
        break;
    }
    
    setStrength(Math.min(score, 5));
  };

  const getColorClass = () => {
    switch (strength) {
      case 0:
      case 1:
        return 'bg-red-500';
      case 2:
        return 'bg-orange-500';
      case 3:
      case 4:
        return 'bg-yellow-500';
      case 5:
        return 'bg-green-500';
      default:
        return 'bg-gray-200';
    }
  };

  return (
    <div className="mt-2">
      <div className="w-full bg-gray-200 h-2 mb-2 rounded-full">
        <div 
          className={`h-full rounded-full ${getColorClass()}`} 
          style={{ width: `${(strength / 5) * 100}%` }}
        ></div>
      </div>
      {feedback && (
        <p className="text-sm text-gray-600">Password strength: <span className="font-medium">{feedback}</span></p>
      )}
    </div>
  );
}