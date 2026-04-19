import React from 'react';
import { AlertTriangle } from './Icons';

interface UpgradePromptProps {
    title: string;
    description: string;
    features?: string[];
}

export const UpgradePrompt: React.FC<UpgradePromptProps> = ({ 
    title, 
    description, 
    features = [] 
}) => {
    return (
        <div className="aicoagac-upgrade-prompt" style={{
            backgroundColor: '#f8f9fa',
            border: '1px solid #e9ecef',
            borderRadius: '8px',
            padding: '20px',
            marginTop: '16px'
        }}>
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
                <AlertTriangle style={{ 
                    width: '20px', 
                    height: '20px', 
                    color: '#6c757d',
                    flexShrink: 0,
                    marginTop: '2px'
                }} />
                <div style={{ flex: 1 }}>
                    <h4 style={{ 
                        margin: '0 0 8px 0',
                        fontSize: '16px',
                        fontWeight: '600',
                        color: '#212529'
                    }}>
                        {title}
                    </h4>
                    <p style={{ 
                        margin: '0 0 12px 0',
                        fontSize: '14px',
                        color: '#6c757d',
                        lineHeight: '1.5'
                    }}>
                        {description}
                    </p>
                    {features.length > 0 && (
                        <ul style={{ 
                            margin: '0',
                            paddingLeft: '20px',
                            fontSize: '13px',
                            color: '#6c757d'
                        }}>
                            {features.map((feature, index) => (
                                <li key={index} style={{ marginBottom: '4px' }}>
                                    {feature}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </div>
    );
};

export default UpgradePrompt;