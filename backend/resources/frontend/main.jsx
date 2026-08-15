import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import axios from 'axios';
import './style.css';

const api = axios.create({
    baseURL: '/smart-gate/api',
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

const FEATURES = {
    resident: ['Vehicles', 'Visitors', 'Requests', 'Tickets'],
    guard: ['Gate Control', 'Walk-in Visitors', 'Blacklist', 'Gate Logs', 'Tickets'],
    admin: ['Gate Control', 'Vehicles', 'Users', 'Blacklist', 'Gate Logs', 'Admin/Guard Logs', 'RFID', 'Walk-in Visitors', 'Tickets', 'Settings'],
    super_admin: ['Gate Control', 'Vehicles', 'Users', 'Blacklist', 'Gate Logs', 'Admin/Guard Logs', 'RFID', 'Walk-in Visitors', 'Tickets', 'Settings'],
};

function useTheme() {
    const [theme, setTheme] = useState(() => localStorage.getItem('smart-gate-theme') || 'light');

    useEffect(() => {
        document.documentElement.dataset.theme = theme;
        localStorage.setItem('smart-gate-theme', theme);
    }, [theme]);

    return [theme, setTheme];
}

function Login({ onLogin }) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');

    return (
        <main className="center-screen">
            <form
                className="glass-card login-card"
                onSubmit={async (event) => {
                    event.preventDefault();
                    try {
                        const response = await api.post('/auth/login', {
                            email,
                            password,
                        });
                        onLogin(response.data.user);
                    } catch (requestError) {
                        setError(requestError.response?.data?.message || 'Login failed.');
                    }
                }}
            >
                <div className="eyebrow">GOLDEN HOMES</div>
                <h1>Smart Gate</h1>
                <p className="muted">Secure gate, visitor, RFID, and account management.</p>

                <label className="field-label" htmlFor="email">Email</label>
                <input
                    id="email"
                    className="input"
                    type="email"
                    placeholder="Email"
                    value={email}
                    onChange={(event) => setEmail(event.target.value)}
                    required
                />

                <label className="field-label" htmlFor="password">Password</label>
                <input
                    id="password"
                    className="input"
                    type="password"
                    placeholder="Password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                    required
                />

                {error && <p className="error">{error}</p>}
                <button className="btn btn-primary btn-block" type="submit">Login</button>
            </form>
        </main>
    );
}

function VisitorStatus() {
    const [credential, setCredential] = useState('');
    const [result, setResult] = useState(null);

    return (
        <section className="glass-card visitor-card">
            <div className="eyebrow">VISITOR ACCESS</div>
            <h2>Check Status (for Visitors)</h2>
            <p className="muted">Enter your six-character Visitor ID.</p>
            <form
                className="inline-form"
                onSubmit={async (event) => {
                    event.preventDefault();
                    try {
                        const response = await api.get(`/visitor/${credential.toUpperCase()}`);
                        setResult(response.data);
                    } catch {
                        setResult({ status: 'not_found' });
                    }
                }}
            >
                <input
                    className="input"
                    maxLength={6}
                    value={credential}
                    onChange={(event) => setCredential(event.target.value.toUpperCase())}
                    placeholder="ABC123"
                    required
                />
                <button className="btn btn-primary" type="submit">Check Status</button>
            </form>
            {result && <div className="status-pill">{result.status.toUpperCase()}</div>}
        </section>
    );
}

function Sidebar({ features, collapsed, onToggle }) {
    return (
        <aside className={`sidebar ${collapsed ? 'sidebar-collapsed' : ''}`}>
            <div className="sidebar-brand">
                <div className="brand-mark">GH</div>
                {!collapsed && (
                    <div>
                        <strong>Smart Gate</strong>
                        <span>Golden Homes</span>
                    </div>
                )}
            </div>

            <nav className="sidebar-nav" aria-label="Main navigation">
                {features.map((feature) => (
                    <button className="sidebar-item" key={feature} title={collapsed ? feature : undefined} type="button">
                        <span className="sidebar-icon" aria-hidden="true">{feature.slice(0, 1)}</span>
                        {!collapsed && <span>{feature}</span>}
                    </button>
                ))}
            </nav>

            <button className="sidebar-collapse" onClick={onToggle} type="button">
                <span>{collapsed ? '›' : '‹'}</span>
                {!collapsed && <span>Minimize sidebar</span>}
            </button>
        </aside>
    );
}

function Dashboard({ user, onLogout }) {
    const [events, setEvents] = useState([]);
    const [afterId, setAfterId] = useState(0);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [theme, setTheme] = useTheme();
    const features = useMemo(() => FEATURES[user.role] || [], [user.role]);

    useEffect(() => {
        const timer = setInterval(async () => {
            try {
                const response = await api.get('/rfid/events', {
                    params: { after_id: afterId },
                });
                if (response.data.length) {
                    setEvents((current) => [...response.data, ...current].slice(0, 50));
                    setAfterId(response.data.at(-1).id);
                }
            } catch {
                // Keep the dashboard usable if the event endpoint is temporarily unavailable.
            }
        }, 750);

        return () => clearInterval(timer);
    }, [afterId]);

    return (
        <div className={`app-shell ${sidebarCollapsed ? 'sidebar-is-collapsed' : ''}`}>
            <Sidebar
                features={features}
                collapsed={sidebarCollapsed}
                onToggle={() => setSidebarCollapsed((value) => !value)}
            />

            <div className="app-content">
                <header className="topbar">
                    <div className="topbar-left">
                        <button
                            className="icon-button sidebar-toggle"
                            type="button"
                            aria-label={sidebarCollapsed ? 'Expand sidebar' : 'Minimize sidebar'}
                            onClick={() => setSidebarCollapsed((value) => !value)}
                        >
                            <span aria-hidden="true">☰</span>
                        </button>
                        <div className="topbar-title">
                            <strong>Dashboard</strong>
                            <span>Smart Gate Control Center</span>
                        </div>
                    </div>

                    <div className="topbar-actions">
                        <button
                            className="theme-toggle"
                            type="button"
                            onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
                            aria-label={`Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`}
                        >
                            <span aria-hidden="true">{theme === 'dark' ? '☀' : '☾'}</span>
                            <span className="theme-label">{theme === 'dark' ? 'Light' : 'Dark'}</span>
                        </button>

                        <div className="account-menu">
                            <button className="account-trigger" type="button">
                                <span className="avatar">{user.full_name?.slice(0, 1)?.toUpperCase() || 'U'}</span>
                                <span className="account-copy">
                                    <strong>{user.full_name}</strong>
                                    <small>{user.role}</small>
                                </span>
                                <span className="chevron">⌄</span>
                            </button>
                            <div className="account-dropdown">
                                <div className="dropdown-user">{user.full_name}<small>{user.role}</small></div>
                                <button
                                    className="dropdown-action"
                                    type="button"
                                    onClick={async () => {
                                        await api.post('/auth/logout');
                                        onLogout();
                                    }}
                                >
                                    Log out
                                </button>
                            </div>
                        </div>
                    </div>
                </header>

                <main className="page-content">
                    <section className="page-heading">
                        <div>
                            <div className="eyebrow">OVERVIEW</div>
                            <h1>Welcome back, {user.full_name?.split(' ')[0] || 'there'}</h1>
                            <p className="muted">Manage your Smart Gate features from one place.</p>
                        </div>
                    </section>

                    <section className="feature-grid">
                        {features.map((feature) => (
                            <button className="feature-card" key={feature} type="button">
                                <span className="feature-icon">{feature.slice(0, 1)}</span>
                                <span className="feature-copy">
                                    <strong>{feature}</strong>
                                    <small>Open module</small>
                                </span>
                                <span className="feature-arrow">›</span>
                            </button>
                        ))}
                    </section>

                    <section className="glass-card live-card">
                        <div className="section-heading">
                            <div>
                                <div className="eyebrow">LIVE MONITOR</div>
                                <h2>RFID Activity</h2>
                            </div>
                            <span className="live-indicator"><i /> Live</span>
                        </div>

                        {events.length === 0 ? (
                            <div className="empty-state">Waiting for RFID activity…</div>
                        ) : (
                            <div className="event-list">
                                {events.map((event) => (
                                    <div className="event-row" key={event.id}>
                                        <span className={`event-status ${event.gate_status === 'approved' ? 'status-ok' : 'status-bad'}`}>
                                            {event.gate_status === 'approved' ? 'GATE OPENED' : 'DENIED'}
                                        </span>
                                        <span>{event.reader === 'entry' ? 'Entry Gate' : 'Exit Gate'}</span>
                                        <code>{event.rfid_uid}</code>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </main>
            </div>
        </div>
    );
}

function App() {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api.get('/auth/me')
            .then((response) => setUser(response.data.user))
            .catch(() => setUser(null))
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return <div className="center-screen"><div className="loading-card">Loading Smart Gate…</div></div>;
    }

    if (user) {
        return <Dashboard user={user} onLogout={() => setUser(null)} />;
    }

    return (
        <>
            <Login onLogin={setUser} />
            <div className="public-status"><VisitorStatus /></div>
        </>
    );
}

createRoot(document.getElementById('root')).render(<App />);
