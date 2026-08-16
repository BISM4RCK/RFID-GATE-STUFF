import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import axios from 'axios';
import JsBarcode from 'jsbarcode';
import './styles.css';

axios.defaults.withCredentials = true;
axios.defaults.headers.common.Accept = 'application/json';

const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    headers: { Accept: 'application/json' },
    validateStatus: status => status >= 200 && status < 500,
});

function useTheme() {
    const [dark, setDark] = useState(() => localStorage.getItem('smart-gate-theme') === 'dark');
    useEffect(() => {
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        localStorage.setItem('smart-gate-theme', dark ? 'dark' : 'light');
    }, [dark]);
    return [dark, () => setDark(v => !v)];
}

function Shell({ children, onHome, user, onLogout, dashboard = false, menuItems = [] }) {
    const [dark, toggleTheme] = useTheme();
    return (
        <div className={`app-shell ${dashboard ? 'dashboard-shell' : ''}`}>
            <header className="topbar">
                <button className="brand" onClick={onHome} aria-label="Go to home">
                    <span className="brand-mark">G</span>
                    <span className="brand-name">Golden Homes</span>
                </button>
                <div className="topbar-actions">
                    {dashboard && user ? <ControlCenter dark={dark} toggleTheme={toggleTheme} onLogout={onLogout}/> : null}
                </div>
            </header>
            {children}
            <footer><span>Golden Homes 2026</span><span>KUN3H0/BISM4RCK 2026</span></footer>
        </div>
    );
}

function ControlCenter({dark,toggleTheme,onLogout}) {
    const [open,setOpen]=useState(false);
    useEffect(()=>{const close=()=>setOpen(false);window.addEventListener('click',close);return()=>window.removeEventListener('click',close)},[]);
    return <div className="control-center" onClick={e=>e.stopPropagation()}>
        <button className="control-center-trigger" onClick={()=>setOpen(v=>!v)} aria-expanded={open}>☷ <span>Controls</span> <span>⌄</span></button>
        {open&&<div className="control-center-popover">
            <button onClick={toggleTheme}><span>{dark?'☀':'☾'}</span>{dark?'Light mode':'Dark mode'}</button>
            <button onClick={onLogout}><span>↪</span>Logout</button>
        </div>}
    </div>;
}

function Modal({ title, children, onClose, wide = false }) {
    return (
        <div className="modal-backdrop" role="dialog" aria-modal="true" onMouseDown={e => e.target === e.currentTarget && onClose()}>
            <div className={`modal ${wide ? 'modal-wide' : ''}`}>
                <div className="modal-head"><h2>{title}</h2><button className="icon-button" onClick={onClose}>×</button></div>
                {children}
            </div>
        </div>
    );
}

function Landing({ navigate }) {
    return (
        <Shell onHome={() => navigate('home')}>
            <section className="hero">
                <div className="hero-copy">
                    <p className="eyebrow">GOLDEN HOMES</p>
                    <h1>Welcome.</h1>
                    <p className="hero-text">Access the gate system, submit a visitor request, or check an existing visitor credential.</p>
                    <div className="button-grid">
                        <button className="primary action-button" onClick={() => navigate('login')}>Login</button>
                        <button className="secondary action-button" onClick={() => navigate('visitor')}>Visitor</button>
                        <button className="secondary action-button" onClick={() => navigate('status')}>Check Status <span className="muted-label">(for visitors)</span></button>
                    </div>
                </div>
            </section>
        </Shell>
    );
}

function Login({ navigate, onLogin }) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    async function submit(e) {
        e.preventDefault(); setError(''); setBusy(true);
        try {
            const r = await api.post('/auth/login', { email, password });
            if (r.status >= 300 || !r.data?.ok) throw new Error(r.data?.message || 'Unable to sign in.');
            onLogin(r.data.user);
        } catch (err) {
            setError(err.response?.data?.message || err.message || 'Invalid email or password.');
        } finally { setBusy(false); }
    }

    return (
        <Shell onHome={() => navigate('home')}>
            <section className="form-card narrow">
                <button className="back-link" onClick={() => navigate('home')}>← Back</button>
                <p className="eyebrow">ACCOUNT ACCESS</p><h1>Sign in</h1>
                <p className="muted">Use your Golden Homes account credentials.</p>
                {error && <div className="alert error">{error}</div>}
                <form onSubmit={submit} className="form-stack">
                    <label>Email<input type="email" autoComplete="username" value={email} onChange={e => setEmail(e.target.value)} required /></label>
                    <label>Password<div className="password-wrap"><input type={showPassword ? "text" : "password"} autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} required /><button type="button" className="password-toggle" onClick={()=>setShowPassword(v=>!v)}>{showPassword?"Hide":"Show"}</button></div></label>
                    <button className="primary action-button" disabled={busy}>{busy ? 'Signing in…' : 'Login'}</button>
                </form>
            </section>
        </Shell>
    );
}

function Visitor({ navigate }) {
    const [form,setForm]=useState({house_number:'',visitor_name:'',contact_number:'',purpose_of_visit:'',people_count:1,vehicles:[{plate_number:'',vehicle_type:'car'}]});
    const [idFile,setIdFile]=useState(null),[result,setResult]=useState(null),[error,setError]=useState(''),[busy,setBusy]=useState(false);
    const update=(k,v)=>setForm(f=>({...f,[k]:v}));
    const updateVehicle=(i,k,v)=>setForm(f=>({...f,vehicles:f.vehicles.map((a,n)=>n===i?{...a,[k]:v}:a)}));
    const addVehicle=()=>form.vehicles.length<20&&setForm(f=>({...f,vehicles:[...f.vehicles,{plate_number:'',vehicle_type:'car'}]}));
    const removeVehicle=i=>setForm(f=>({...f,vehicles:f.vehicles.filter((_,n)=>n!==i)}));
    async function submit(e){e.preventDefault();setError('');setBusy(true);try{const fd=new FormData();fd.append('house_number',form.house_number);fd.append('visitor_name',form.visitor_name);fd.append('contact_number',form.contact_number);fd.append('purpose_of_visit',form.purpose_of_visit);fd.append('people_count',String(form.people_count));form.vehicles.forEach((v,i)=>{fd.append(`vehicles[${i}][plate_number]`,v.plate_number.toUpperCase());fd.append(`vehicles[${i}][vehicle_type]`,v.vehicle_type)});if(idFile)fd.append('government_id',idFile);const r=await api.post('/visitor',fd);if(r.status>=300||!r.data?.ok)throw new Error(r.data?.message||'Unable to submit.');setResult(r.data)}catch(err){setError(err.response?.data?.message||err.message||'Unable to submit the visitor request.')}finally{setBusy(false)}}
    if(result) return <Shell onHome={()=>navigate('home')}><section className="form-card visitor-success"><p className="eyebrow">REQUEST SUBMITTED</p><h1>You're all set.</h1><p className="muted">The resident must approve this visitor request before entry.</p><div className="credential"><span>Visitor barcode</span><strong>{result.visitor_id}</strong><Barcode value={result.visitor_id}/><small className="muted">Keep this Code 128 barcode for the gate.</small></div><div className="button-row"><button className="primary action-button" onClick={()=>navigate('status')}>Check Status</button><button className="secondary action-button" onClick={()=>navigate('home')}>Done</button></div></section></Shell>;
    return <Shell onHome={()=>navigate('home')}><section className="form-card visitor-form"><button className="back-link" onClick={()=>navigate('home')}>← Back</button><p className="eyebrow">VISITOR ACCESS</p><h1>Visitor request</h1><p className="muted">Enter your details. The resident will review your request.</p>{error&&<div className="alert error">{error}</div>}<form onSubmit={submit} className="form-stack"><div className="form-grid">
        <label>Resident house number<input value={form.house_number} onChange={e=>update('house_number',e.target.value)} placeholder="12-4-A" required /></label>
        <label>Visitor name<input value={form.visitor_name} onChange={e=>update('visitor_name',e.target.value)} required /></label>
        <label>Contact number<input value={form.contact_number} onChange={e=>update('contact_number',e.target.value)} /></label>
        <label>People count<input type="number" min="1" max="20" value={form.people_count} onChange={e=>update('people_count',Number(e.target.value))} required /></label>
        <label className="full-width">Purpose of visit<input value={form.purpose_of_visit} onChange={e=>update('purpose_of_visit',e.target.value)} required /></label>
        <div className="full-width"><div className="panel-head"><div><h3>Vehicles</h3><p className="muted">Add up to 10 vehicles. The barcode has a per-gate usage limit based on this count.</p></div><button type="button" className="secondary compact" onClick={addVehicle} disabled={form.vehicles.length>=20}>Add vehicle</button></div>{form.vehicles.map((v,i)=><div className="vehicle-row" key={i}><input placeholder="Plate number" value={v.plate_number} onChange={e=>updateVehicle(i,'plate_number',e.target.value.toUpperCase())} required/><select value={v.vehicle_type} onChange={e=>updateVehicle(i,'vehicle_type',e.target.value)}><option>car</option><option>motorcycle</option><option>truck</option><option>tricycle</option><option>ebike</option><option>other</option></select>{i>0&&<button type="button" className="text-button" onClick={()=>removeVehicle(i)}>Remove</button>}</div>)}</div>
        <label className="full-width">Government ID (optional)<input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={e=>setIdFile(e.target.files?.[0]||null)}/><small className="muted">You may continue without uploading an ID.</small></label>
    </div><button className="primary action-button" disabled={busy}>{busy?'Submitting…':'Submit Visitor Request'}</button></form></section></Shell>;
}

function VisitorStatus({navigate}) {
    const [credential,setCredential]=useState(''),[result,setResult]=useState(null),[error,setError]=useState(''),[busy,setBusy]=useState(false);
    async function check(e){e.preventDefault();setError('');setResult(null);setBusy(true);try{const v=credential.trim().toUpperCase();const r=await api.get(`/visitor/${encodeURIComponent(v)}`);if(r.status>=300||!r.data?.ok)throw new Error(r.status===404?'No visitor request was found for that credential.':'Unable to check status.');setResult(r.data)}catch(err){setError(err.response?.data?.message||err.message||'Unable to check status.')}finally{setBusy(false)}}
    return <Shell onHome={()=>navigate('home')}><section className="form-card narrow"><button className="back-link" onClick={()=>navigate('home')}>← Back</button><p className="eyebrow">VISITOR STATUS</p><h1>Check status</h1><p className="muted">Enter the six-character credential you received.</p>{error&&<div className="alert error">{error}</div>}<form onSubmit={check} className="form-stack"><label>Visitor credential<input value={credential} onChange={e=>setCredential(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,6))} placeholder="ABC123" maxLength={6} required /></label><button className="primary action-button" disabled={busy}>{busy?'Checking…':'Check Status'}</button></form>{result&&<div className={`status-card ${String(result.status).toLowerCase()}`}><span>REQUEST STATUS</span><strong>{String(result.status).replace('_',' ').toUpperCase()}</strong><small>Credential: {result.visitor_id}</small></div>}</section></Shell>;
}

const navFor = role => role === 'guard'
    ? [{id:'overview',icon:'⌂',label:'Overview'},{id:'logs',icon:'▤',label:'Gate Logs'},{id:'visitor-scan',icon:'⌁',label:'Visitor Scan'},{id:'walkins',icon:'＋',label:'Walk-in Visitors'},{id:'blacklist',icon:'⊘',label:'Blacklist'}]
    : role === 'admin'
        ? [{id:'overview',icon:'⌂',label:'Overview'},{id:'logs',icon:'▤',label:'Gate Logs'},{id:'account-logs',icon:'◷',label:'Account Logs'},{id:'walkins',icon:'＋',label:'Walk-in Visitors'},{id:'blacklist',icon:'⊘',label:'Blacklist'},{id:'accounts',icon:'♙',label:'Accounts'},{id:'vehicles',icon:'▣',label:'Vehicles'},{id:'rfid',icon:'◉',label:'RFID Cards'}]
        : [{id:'overview',icon:'⌂',label:'Overview'},{id:'vehicles',icon:'▣',label:'My Vehicles'},{id:'pre-register',icon:'＋',label:'Pre-register Visitor'}];

function Sidebar({user,active,setActive,collapsed,setCollapsed}) {
    const nav=navFor(user.role);
    return <aside className={`sidebar ${collapsed?'is-collapsed':''}`}><div className="sidebar-head"><span className="sidebar-title">{user.role === 'admin'?'Administration':user.role === 'guard'?'Guard Console':'Resident'}</span><button className="icon-button sidebar-collapse" onClick={()=>{setCollapsed(v=>{const next=!v;localStorage.setItem('smart-gate-sidebar',next?'mini':'full');return next})}} title={collapsed?'Expand sidebar':'Minimize sidebar'}>{collapsed?'›':'‹'}</button></div><nav>{nav.map(item=><button key={item.id} className={active===item.id?'active':''} title={item.label} onClick={()=>setActive(item.id)}><span className="nav-icon">{item.icon}</span><span className="nav-label">{item.label}</span></button>)}</nav></aside>;
}

function ReaderPills({readers=[]}) { const by=Object.fromEntries(readers.map(r=>[r.reader,r])); return <div className="reader-row"><ReaderPill name="Entry RFID" reader={by.entry}/><ReaderPill name="Exit RFID" reader={by.exit}/></div>; }
function ReaderPill({name,reader}){const online=!!reader?.online;return <div className={`reader-pill ${online?'online':'offline'}`}><span className="dot"/><span><strong>{name}</strong><small>{reader?.last_seen_at?`Last heartbeat ${manilaTime(reader.last_seen_at)}`:'No heartbeat yet'}</small></span><strong>{online?'Online':'Offline'}</strong></div>}
function Stat({label,value,sub}){return <div className="stat-card"><span>{label}</span><strong>{value}</strong>{sub&&<small>{sub}</small>}</div>}
function manilaTime(value){if(!value)return '—';try{let raw=String(value);let d=/Z$|[+-]\d\d:\d\d$/.test(raw)?new Date(raw):new Date(raw.replace(' ','T')+'+08:00');return new Intl.DateTimeFormat('en-PH',{timeZone:'Asia/Manila',dateStyle:'medium',timeStyle:'medium'}).format(d)}catch{return String(value)}}

function LogFilters({filters,setFilters,account=false}){
    return <div className="log-filters">
        <select value={filters.gate} onChange={e=>setFilters(f=>({...f,gate:e.target.value}))}><option value="all">All gates</option><option value="entry">Entry</option><option value="exit">Exit</option></select>
        {!account&&<select value={filters.result} onChange={e=>setFilters(f=>({...f,result:e.target.value}))}><option value="all">All results</option><option value="approved">Approved</option><option value="denied">Denied</option><option value="manual_override">Override</option></select>}
        <select value={filters.type} onChange={e=>setFilters(f=>({...f,type:e.target.value}))}><option value="all">All actions</option>{account?<><option value="login">Login</option><option value="logout">Logout</option><option value="add_vehicle">Add vehicle</option><option value="remove_vehicle">Remove vehicle</option><option value="gate_override_requested">Gate override</option><option value="gate_override_acknowledged">Override acknowledged</option><option value="add_blacklist">Add blacklist</option><option value="visitor_barcode_scan">Visitor scan</option><option value="approve_visitor">Approve visitor</option><option value="reject_visitor">Reject visitor</option></>:<><option value="rfid_scan">RFID scan</option><option value="visitor_barcode_scan">Visitor barcode</option><option value="manual_override">Gate override</option></>}</select>
    </div>
}

function filterLogs(logs,filters){return logs.filter(x=>(filters.gate==='all'||x.reader===filters.gate)&&(filters.result==='all'||x.gate_status===filters.result)&&(filters.type==='all'||x.event_type===filters.type));}
function LogTable({logs}){return <div className="table-wrap"><table><thead><tr><th>Time (GMT+8)</th><th>Gate</th><th>Account / Visitor</th><th>Plate</th><th>Type</th><th>Result</th></tr></thead><tbody>{logs.length?logs.map(log=><tr key={log.id}><td>{manilaTime(log.created_at)}</td><td>{(log.reader||'—').toUpperCase()}</td><td>{log.account_name||log.actor_name||log.account_email||log.log_notes||'Visitor'}</td><td>{log.plate_number||'—'}</td><td>{String(log.event_type||'').replaceAll('_',' ')}</td><td><span className={`badge ${log.gate_status}`}>{log.gate_status}</span></td></tr>):<tr><td colSpan="6" className="empty">No matching gate logs.</td></tr>}</tbody></table></div>}

function InlineOverride({user,onModal}){return <section className="panel inline-override"><PanelHead title="Emergency gate override" subtitle="Emergency opening requires acknowledgement before the ESP32 receives the command."/><div className="override-grid"><button className="danger large-control" onClick={()=>onModal('override-entry')}>Open Entry Gate</button><button className="danger large-control" onClick={()=>onModal('override-exit')}>Open Exit Gate</button></div></section>}
function GateAnimation({gate,state='closed',readerOnline}){
    return <div className={`gate-card ${state}`}><div className="gate-card-head"><div><span className="eyebrow">{gate.toUpperCase()} GATE</span><h3>{state==='open'?'OPEN':state==='opening'?'OPENING':'CLOSED'}</h3></div><span className={`mini-status ${readerOnline?'online':'offline'}`}>{readerOnline?'Reader online':'Reader offline'}</span></div><div className="gate-scene"><div className="gate-road"/><div className="gate-arm"/><div className="gate-post"/><div className="gate-light"/></div><small>{state==='open'?'Gate open activity detected.':state==='opening'?'Opening command active.':'Ready for access.'}</small></div>
}
function GateAnimations({readers,states}){const by=Object.fromEntries((readers||[]).map(r=>[r.reader,r]));return <div className="gate-grid"><GateAnimation gate="entry" state={states.entry} readerOnline={!!by.entry?.online}/><GateAnimation gate="exit" state={states.exit} readerOnline={!!by.exit?.online}/></div>}

function StaffDashboard({user,onLogout}) {
    const [active,setActive]=useState('overview'),[collapsed,setCollapsed]=useState(() => localStorage.getItem('smart-gate-sidebar') === 'mini'),[data,setData]=useState({readers:[],recent_logs:[]}),[logs,setLogs]=useState([]),[error,setError]=useState(''),[modal,setModal]=useState(null),[rfidEvent,setRfidEvent]=useState(null),[states,setStates]=useState({entry:'closed',exit:'closed'}),[accountLogs,setAccountLogs]=useState([]);
    const lastIdRef=useRef(0); const timers=useRef({entry:null,exit:null});
    const [blacklist,setBlacklist]=useState([]),[walkins,setWalkins]=useState([]),[users,setUsers]=useState([]),[vehicles,setVehicles]=useState([]),[cards,setCards]=useState([]);
    const [filters,setFilters]=useState({gate:'all',result:'all',type:'all'}),[accountFilters,setAccountFilters]=useState({gate:'all',result:'all',type:'all'});

    async function loadOverview(initial=false){const r=await api.get('/staff/overview');if(r.status>=300||!r.data?.ok){setError(r.data?.message||'Unable to load dashboard.');return}setData(r.data);if(initial){const seed=r.data.recent_logs||[];setLogs(seed);lastIdRef.current=Math.max(0,...seed.map(x=>x.id));}}
    function animateGate(gate){setStates(s=>({...s,[gate]:'opening'}));setTimeout(()=>setStates(s=>({...s,[gate]:'open'})),180);clearTimeout(timers.current[gate]);timers.current[gate]=setTimeout(()=>setStates(s=>({...s,[gate]:'closed'})),3200);}
    async function pollLogs(){const r=await api.get(`/staff/logs?after_id=${lastIdRef.current}&limit=50`);if(r.status>=300||!r.data?.ok)return;const fresh=r.data.logs||[];if(!fresh.length)return;const ordered=[...fresh].sort((a,b)=>a.id-b.id);setLogs(prev=>[...ordered,...prev].slice(0,300));lastIdRef.current=Math.max(lastIdRef.current,...ordered.map(x=>x.id));ordered.forEach(ev=>{if(ev.reader&&(['approved','manual_override'].includes(ev.gate_status)||ev.event_type==='visitor_barcode_scan'))animateGate(ev.reader);if(ev.event_type==='rfid_scan')setRfidEvent(ev);});}
    async function loadLists(){const [b,w]=await Promise.all([api.get('/staff/blacklist'),api.get('/staff/walkins')]);if(b.data?.ok)setBlacklist(b.data.items||[]);if(w.data?.ok)setWalkins(w.data.items||[]);if(user.role==='admin'){const [u,v,c,a]=await Promise.all([api.get('/admin/users'),api.get('/admin/vehicles'),api.get('/admin/rfid-cards'),api.get('/admin/account-logs')]);if(u.data?.ok)setUsers(u.data.users||[]);if(v.data?.ok)setVehicles(v.data.vehicles||[]);if(c.data?.ok)setCards(c.data.cards||[]);if(a.data?.ok)setAccountLogs(a.data.logs||[]);}}
    useEffect(()=>{loadOverview(true);loadLists();const timer=setInterval(()=>{loadOverview(false);pollLogs();if(user.role==='admin')api.get('/admin/account-logs').then(r=>r.data?.ok&&setAccountLogs(r.data.logs||[]));},2500);return()=>{clearInterval(timer);Object.values(timers.current).forEach(clearTimeout)}},[]);
    async function logout(){try{await api.post('/auth/logout')}finally{onLogout()}}
    const currentLogs=logs.length?logs:(data.recent_logs||[]); const filtered=filterLogs(currentLogs,filters);
    const nav=navFor(user.role); const menuItems=nav.map(i=>({...i,onClick:()=>setActive(i.id)}));
    const content={
        overview:<Overview user={user} data={data} logs={currentLogs.slice(0,10)} onAction={setActive} onModal={setModal} states={states}/>,
        logs:<section className="panel"><PanelHead title="Gate logs" subtitle="Live entry and exit activity. Updates automatically."/><LogFilters filters={filters} setFilters={setFilters}/><LogTable logs={filtered}/></section>,
        'visitor-scan':user.role==='guard'?<section className="panel"><PanelHead title="Visitor barcode" subtitle="Scan a real barcode and select the gate in a popup."/><FeatureCard title="Scan visitor barcode" text="Camera scanner with entry/exit selection. No QR scanner." button="Open scanner" onClick={()=>setModal('visitor-scan')}/></section>:null,
        walkins:<WalkInPanel items={walkins} onAdd={()=>setModal('walkin')} />,
        blacklist:<BlacklistPanel items={blacklist} onAdd={()=>setModal('blacklist')} onRemove={async id=>{await api.delete(`/staff/blacklist/${id}`);loadLists()}}/>,
        
        accounts:<AdminAccounts/>, vehicles:<AdminVehicles/>, rfid:<AdminRfid cards={cards}/>,
        'override-entry':<section className="panel"><PanelHead title="Emergency gate override" subtitle="Emergency controls are available directly from Overview."/><OverridePanel onOpen={gate=>setModal('override-'+gate)}/></section>,
        'override-exit':<section className="panel"><PanelHead title="Emergency gate override" subtitle="Emergency controls are available directly from Overview."/><OverridePanel onOpen={gate=>setModal('override-'+gate)}/></section>,
        'account-logs':<section className="panel"><PanelHead title="Account logs" subtitle="Resident and staff account activity — Manila time (GMT+8)."/><LogFilters filters={accountFilters} setFilters={setAccountFilters} account/><AccountLogTable logs={filterLogs(accountLogs,{...accountFilters,gate:'all',result:'all'})}/></section>,
    };
    return <Shell dashboard user={user} onHome={()=>setActive('overview')} onLogout={logout} menuItems={menuItems}><Sidebar user={user} active={active} setActive={setActive} collapsed={collapsed} setCollapsed={setCollapsed}/><main className={`dashboard-page ${collapsed ? 'sidebar-collapsed' : ''}`}><div className="mobile-sticky-nav">{nav.map(i=><button key={i.id} className={active===i.id?'active':''} onClick={()=>setActive(i.id)}><span>{i.icon}</span><small>{i.label}</small></button>)}</div><div className="dashboard-content">{error&&<div className="alert error">{error}</div>}{content[active]||content.overview}</div></main>{modal==='visitor-scan'&&<VisitorScanModal onClose={()=>setModal(null)} onDone={()=>{setModal(null);pollLogs()}}/>}{modal==='walkin'&&<WalkInModal onClose={()=>setModal(null)} onDone={()=>{setModal(null);loadLists()}}/>}{modal==='blacklist'&&<BlacklistModal onClose={()=>setModal(null)} onDone={()=>{setModal(null);loadLists()}}/>}{modal?.startsWith('override-')&&<OverrideModal gate={modal.split('-')[1]} onClose={()=>setModal(null)} onDone={(gate)=>{setModal(null);animateGate(gate);loadOverview(false);pollLogs()}}/>}{rfidEvent&&<RfidPopup event={rfidEvent} onClose={()=>setRfidEvent(null)}/>}</Shell>;
}

function PanelHead({title,subtitle,action}){return <div className="panel-head"><div><h2>{title}</h2><p className="muted">{subtitle}</p></div>{action}</div>}
function Overview({user,data,logs,onAction,onModal,states}){return <><section className="dashboard-head"><div><p className="eyebrow">{user.role==='admin'?'ADMIN DASHBOARD':'GUARD DASHBOARD'}</p><h1>Welcome, {user.full_name}</h1><p className="muted">{user.email}</p></div></section><ReaderPills readers={data.readers}/><InlineOverride user={user} onModal={onModal}/><GateAnimations readers={data.readers} states={states}/><div className="stats"><Stat label="Recent gate logs" value={logs.length}/><Stat label="Active blacklist" value={data.blacklist_count||0}/><Stat label="Walk-in visitors" value={data.walk_in_count||0}/></div><section className="panel"><PanelHead title="Recent entry & exit logs" subtitle="The latest ten gate events update automatically." action={<button className="secondary compact" onClick={()=>onAction('logs')}>All logs</button>}/><LogTable logs={logs}/></section><section className="feature-grid">{user.role==='guard'&&<FeatureCard title="Visitor barcode" text="Scan a real visitor barcode and choose entry or exit in a popup." button="Scan visitor" onClick={()=>onModal('visitor-scan')}/>}<FeatureCard title="Walk-in visitor" text="Create a temporary visitor credential from the dashboard." button="Add walk-in" onClick={()=>onAction('walkins')}/><FeatureCard title="Blacklist" text="Block a visitor or vehicle from entry." button="Manage blacklist" onClick={()=>onAction('blacklist')}/><div className="feature-card gate-override-card"><h3>Emergency gate override</h3><p>Emergency opening requires acknowledgement before the ESP32 receives the command.</p><div className="button-row"><button className="danger compact" onClick={()=>onModal('override-entry')}>Open Entry</button><button className="danger compact" onClick={()=>onModal('override-exit')}>Open Exit</button></div></div>{user.role==='admin'&&<FeatureCard title="Account logs" text="Review resident and staff login and action history." button="View account logs" onClick={()=>onAction('account-logs')}/>}</section></>}
function FeatureCard({title,text,button,onClick}){return <div className="feature-card"><h3>{title}</h3><p>{text}</p><button className="secondary" onClick={onClick}>{button}</button></div>}

function Barcode({value}){const ref=useRef(null);useEffect(()=>{if(ref.current&&value){try{JsBarcode(ref.current,value,{format:'CODE128',displayValue:true,height:64,margin:8,lineColor:'currentColor',background:'transparent'})}catch{}}},[value]);return <svg ref={ref} className="barcode" aria-label={`Barcode ${value}`}/>}

function VisitorScanModal({onClose,onDone}){
    const [gate,setGate]=useState('entry'),[credential,setCredential]=useState(''),[error,setError]=useState(''),[result,setResult]=useState(null),[scanning,setScanning]=useState(false);const video=useRef(null),stream=useRef(null),raf=useRef(null);
    async function start(){setError('');if(!('BarcodeDetector' in window)){setError('This browser does not provide a live barcode detector. Enter the barcode value below.');return}try{const detector=new window.BarcodeDetector({formats:['code_128','ean_13','ean_8','upc_a','upc_e','codabar','itf']});stream.current=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}}});video.current.srcObject=stream.current;await video.current.play();setScanning(true);const loop=async()=>{if(!video.current||video.current.readyState<2)return raf.current=requestAnimationFrame(loop);try{const codes=await detector.detect(video.current);if(codes[0]?.rawValue){setCredential(codes[0].rawValue);stop();}}catch{}raf.current=requestAnimationFrame(loop)};loop();}catch(e){setError(e.message||'Camera access failed.')}}
    function stop(){setScanning(false);if(raf.current)cancelAnimationFrame(raf.current);if(stream.current){stream.current.getTracks().forEach(t=>t.stop());stream.current=null}}
    useEffect(()=>()=>stop(),[]);
    async function submit(e){e.preventDefault();setError('');const r=await api.post('/staff/visitor-scan',{credential:credential.trim(),gate});if(r.status>=300||!r.data?.ok)throw new Error(r.data?.message||'Scan failed.');setResult(r.data);onDone();}
    return <Modal title="Scan visitor barcode" onClose={()=>{stop();onClose()}}><div className="segmented"><button className={gate==='entry'?'selected':''} onClick={()=>setGate('entry')}>Entry gate</button><button className={gate==='exit'?'selected':''} onClick={()=>setGate('exit')}>Exit gate</button></div><video ref={video} className="scanner-video" muted playsInline/><div className="button-row"><button className="secondary" type="button" onClick={scanning?stop:start}>{scanning?'Stop camera':'Start camera scanner'}</button></div>{error&&<div className="alert error">{error}</div>}<form className="form-stack" onSubmit={submit}><label>Barcode value<input value={credential} onChange={e=>setCredential(e.target.value)} placeholder="Scan Code 128 barcode" required/></label><button className="primary" disabled={!credential.trim()}>Check visitor</button></form>{result&&<div className={`scan-result ${result.gate_status}`}><span>{result.gate.toUpperCase()} GATE</span><strong>{result.gate_opened?'GATE OPENED':'ACCESS DENIED'}</strong><span>{result.visitor?.name||'Unknown visitor'} · {result.visitor?.plate_number||'No plate'}</span><small>{result.reason}</small></div>}</Modal>
}

function WalkInModal({onClose,onDone}){const [f,setF]=useState({visitor_name:'',contact_number:'',purpose_of_visit:'',plate_number:'',vehicle_type:'car',people_count:1}),[error,setError]=useState(''),[busy,setBusy]=useState(false),[created,setCreated]=useState(null);const u=(k,v)=>setF(x=>({...x,[k]:v}));async function submit(e){e.preventDefault();setBusy(true);try{const r=await api.post('/staff/walkins',f);if(r.status>=300||!r.data?.ok)throw new Error(r.data?.message||'Unable to create walk-in.');setCreated(r.data)}catch(e){setError(e.response?.data?.message||e.message)}finally{setBusy(false)}}return <Modal title="Add walk-in visitor" onClose={onClose}><p className="muted">Create a temporary visitor credential with a real Code 128 barcode.</p>{error&&<div className="alert error">{error}</div>}<form onSubmit={submit} className="form-grid"><label>Visitor name<input value={f.visitor_name} onChange={e=>u('visitor_name',e.target.value)} required/></label><label>Contact<input value={f.contact_number} onChange={e=>u('contact_number',e.target.value)}/></label><label>Purpose<input value={f.purpose_of_visit} onChange={e=>u('purpose_of_visit',e.target.value)} required/></label><label>Plate<input value={f.plate_number} onChange={e=>u('plate_number',e.target.value.toUpperCase())}/></label><label>Vehicle<select value={f.vehicle_type} onChange={e=>u('vehicle_type',e.target.value)}><option>car</option><option>motorcycle</option><option>truck</option><option>tricycle</option><option>ebike</option><option>other</option></select></label><label>People<input type="number" min="1" max="20" value={f.people_count} onChange={e=>u('people_count',Number(e.target.value))}/></label><div className="full-width button-row"><button className="secondary" type="button" onClick={onClose}>Cancel</button><button className="primary" disabled={busy}>{busy?'Creating…':'Create visitor'}</button></div></form>{created&&<div className="credential"><span>Visitor credential</span><strong>{created.visitor_id}</strong><Barcode value={created.visitor_id}/><button className="secondary compact" onClick={onDone}>Done</button></div>}</Modal>}

function BlacklistModal({onClose,onDone}){const [f,setF]=useState({visitor_name:'',plate_number:'',reason:'',start_date:'',end_date:''}),[error,setError]=useState(''),[busy,setBusy]=useState(false);const u=(k,v)=>setF(x=>({...x,[k]:v}));async function submit(e){e.preventDefault();setBusy(true);try{const r=await api.post('/staff/blacklist',f);if(r.status>=300||!r.data?.ok)throw new Error(r.data?.message||'Unable to add blacklist entry.');onDone()}catch(e){setError(e.response?.data?.message||e.message)}finally{setBusy(false)}}return <Modal title="Add blacklist entry" onClose={onClose}><p className="muted">Provide a visitor name or plate number and a reason.</p>{error&&<div className="alert error">{error}</div>}<form onSubmit={submit} className="form-grid"><label>Visitor name<input value={f.visitor_name} onChange={e=>u('visitor_name',e.target.value)}/></label><label>Plate number<input value={f.plate_number} onChange={e=>u('plate_number',e.target.value.toUpperCase())}/></label><label className="full-width">Reason<input value={f.reason} onChange={e=>u('reason',e.target.value)} required/></label><label>Start date<input type="date" value={f.start_date} onChange={e=>u('start_date',e.target.value)}/></label><label>End date<input type="date" value={f.end_date} onChange={e=>u('end_date',e.target.value)}/></label><div className="full-width button-row"><button type="button" className="secondary" onClick={onClose}>Cancel</button><button className="primary" disabled={busy}>{busy?'Saving…':'Add to blacklist'}</button></div></form></Modal>}

function OverrideModal({gate,onClose,onDone}){const [busy,setBusy]=useState(false),[error,setError]=useState(''),[command,setCommand]=useState(null),[ack,setAck]=useState(false);async function request(){setBusy(true);try{const r=await api.post('/gate/override',{gate,emergency:true});if(r.status>=300||!r.data?.ok)throw new Error(r.data?.message||'Override failed.');setCommand(r.data)}catch(e){setError(e.response?.data?.message||e.message)}finally{setBusy(false)}}async function acknowledge(){setBusy(true);try{const r=await api.post(`/gate/override/${command.command_id}/acknowledge`);if(r.status>=300||!r.data?.ok)throw new Error(r.data?.message||'Acknowledgement failed.');setAck(true);onDone(gate)}catch(e){setError(e.response?.data?.message||e.message)}finally{setBusy(false)}}return <Modal title={`${gate==='entry'?'Entry':'Exit'} gate emergency override`} onClose={onClose}><div className="danger-box"><strong>Emergency action</strong><p>The gate command will not reach the ESP32 until you acknowledge this emergency action.</p></div>{error&&<div className="alert error">{error}</div>}{!command?<div className="button-row"><button className="secondary" onClick={onClose}>Cancel</button><button className="danger" onClick={request} disabled={busy}>{busy?'Sending…':'Request gate opening'}</button></div>:<div className="ack-card"><strong>Override request created.</strong><p>Command #{command.command_id} is waiting for acknowledgement.</p><button className="danger" onClick={acknowledge} disabled={busy||ack}>{ack?'Acknowledged':'Acknowledge & open gate'}</button></div>}</Modal>}

function RfidPopup({event,onClose}){return <Modal title="RFID card scanned" onClose={onClose}><div className="rfid-popup"><div className="scan-icon">◉</div><div className="scan-row"><span>Gate</span><strong>{String(event.reader||'').toUpperCase()}</strong></div><div className="scan-row"><span>Account</span><strong>{event.account_name||event.account_email||'Unknown / unregistered'}</strong></div><div className="scan-row"><span>Vehicle plate</span><strong>{event.plate_number||'—'}</strong></div><div className="scan-row"><span>RFID</span><strong>{event.rfid_uid||'—'}</strong></div><div className={`decision ${event.gate_status}`}>{event.gate_status==='approved'?'GATE OPENED':'ACCESS DENIED'}</div><p className="muted">{event.log_notes||''}</p></div></Modal>}

function WalkInPanel({items,onAdd}){return <section className="panel"><PanelHead title="Walk-in visitors" subtitle="Temporary visitors created at the guard desk." action={<button className="primary compact" onClick={onAdd}>Add walk-in</button>}/><div className="table-wrap"><table><thead><tr><th>Credential</th><th>Name</th><th>Plate</th><th>Purpose</th><th>Status</th></tr></thead><tbody>{items.length?items.map(x=><tr key={x.id}><td><strong>{x.visitor_id}</strong></td><td>{x.visitor_name}</td><td>{x.plate_number||'—'}</td><td>{x.purpose_of_visit}</td><td><span className={`badge ${x.status}`}>{x.status}</span></td></tr>):<tr><td colSpan="5" className="empty">No walk-in visitors.</td></tr>}</tbody></table></div></section>}
function BlacklistPanel({items,onAdd,onRemove}){return <section className="panel"><PanelHead title="Blacklist" subtitle="Active entries are checked during RFID and visitor scans." action={<button className="primary compact" onClick={onAdd}>Add blacklist</button>}/><div className="table-wrap"><table><thead><tr><th>Visitor</th><th>Plate</th><th>Reason</th><th>Status</th><th></th></tr></thead><tbody>{items.length?items.map(x=><tr key={x.id}><td>{x.visitor_name||'—'}</td><td>{x.plate_number||'—'}</td><td>{x.reason}</td><td><span className={`badge ${x.status}`}>{x.status}</span></td><td>{x.status==='active'&&<button className="text-button" onClick={()=>onRemove(x.id)}>Deactivate</button>}</td></tr>):<tr><td colSpan="5" className="empty">No blacklist entries.</td></tr>}</tbody></table></div></section>}
function OverridePanel({onOpen}){return <section className="panel"><PanelHead title="Emergency gate override" subtitle="Every emergency opening requires an acknowledgement before the ESP32 receives it."/><div className="override-grid"><button className="danger large-control" onClick={()=>onOpen('entry')}>Open Entry Gate</button><button className="danger large-control" onClick={()=>onOpen('exit')}>Open Exit Gate</button></div></section>}
function AdminUsers({users}){return <section className="panel"><PanelHead title="Users" subtitle="Account status and roles."/><div className="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Super admin</th></tr></thead><tbody>{users.map(u=><tr key={u.id}><td>{u.full_name}</td><td>{u.email}</td><td>{u.role}</td><td>{u.status}</td><td>{u.is_super_admin?'Yes':'No'}</td></tr>)}</tbody></table></div></section>}
function AdminVehicles(){
    const [data,setData]=useState({resident_vehicles:[],staff_vehicles:[]}),[modal,setModal]=useState(false),[error,setError]=useState('');
    async function load(){try{const r=await api.get('/admin/account-vehicles');if(r.data?.ok)setData(r.data)}catch(e){setError(e.response?.data?.message||'Unable to load vehicles.')}}
    useEffect(()=>{load()},[]);
    return <section className="panel"><PanelHead title="Vehicles" subtitle="Resident and staff vehicles are managed separately." action={<button className="primary compact" onClick={()=>setModal(true)}>Add vehicle to account</button>}/>{error&&<div className="alert error">{error}</div>}<h3 className="section-title">Resident vehicles</h3><div className="table-wrap"><table><thead><tr><th>Account</th><th>Phase</th><th>Block-Lot-Letter</th><th>Plate</th><th>Type</th><th>Color</th></tr></thead><tbody>{data.resident_vehicles.length?data.resident_vehicles.map(v=><tr key={'r'+v.id}><td>{v.account_name}</td><td>{v.phase||'—'}</td><td>{[v.block_number,v.lot_number,v.household_letter].filter(Boolean).join('-')||'—'}</td><td>{v.plate_number}</td><td>{String(v.vehicle_type).toUpperCase()}</td><td>{v.color||'—'}</td></tr>):<tr><td colSpan="6" className="empty">No resident vehicles.</td></tr>}</tbody></table></div><h3 className="section-title">Staff vehicles</h3><div className="table-wrap"><table><thead><tr><th>Account</th><th>Role</th><th>Plate</th><th>Type</th><th>Color</th></tr></thead><tbody>{data.staff_vehicles.length?data.staff_vehicles.map(v=><tr key={'s'+v.id}><td>{v.account_name}</td><td>{v.role}</td><td>{v.plate_number}</td><td>{String(v.vehicle_type||'').toUpperCase()}</td><td>{v.color||'—'}</td></tr>):<tr><td colSpan="5" className="empty">No staff vehicles.</td></tr>}</tbody></table></div>{modal&&<AdminVehicleModal onClose={()=>setModal(false)} onDone={()=>{setModal(false);load()}}/>}</section>
}

function AdminVehicleModal({onClose,onDone}){
    const [accounts,setAccounts]=useState({residents:[],staff:[]}),[role,setRole]=useState('resident'),[phase,setPhase]=useState(''),[userId,setUserId]=useState(''),[f,setF]=useState({plate_number:'',vehicle_type:'car',color:''}),[error,setError]=useState(''),[busy,setBusy]=useState(false);
    useEffect(()=>{api.get('/admin/accounts').then(r=>r.data?.ok&&setAccounts(r.data))},[]);
    const list=role==='resident'?accounts.residents.filter(a=>!phase||a.phase===phase):accounts.staff.filter(a=>(role==='admin'?a.role==='admin':a.role==='guard')&&(!gateAssignment||a.gate_assignment===gateAssignment));
    const phases=[...new Set(accounts.residents.map(a=>a.phase).filter(Boolean))];
    useEffect(()=>{setUserId('')},[role,phase,gateAssignment]);
    async function submit(e){e.preventDefault();setBusy(true);setError('');try{const payload={user_id:Number(userId),vehicle_type:f.vehicle_type,color:f.color};if(f.vehicle_type!=='ebike')payload.plate_number=f.plate_number.toUpperCase();const r=await api.post('/admin/vehicles',payload);if(r.status>=300||!r.data?.ok)throw new Error(r.data?.message||'Unable to add vehicle.');onDone()}catch(e){setError(e.response?.data?.message||e.message)}finally{setBusy(false)}}
    return <Modal title="Add vehicle to account" onClose={onClose}><p className="muted">Choose the account first, then add the vehicle.</p>{error&&<div className="alert error">{error}</div>}<form className="form-stack" onSubmit={submit}><label>Account role<select value={role} onChange={e=>setRole(e.target.value)}><option value="resident">Resident</option><option value="guard">Guard</option><option value="admin">Admin</option></select></label>{role==='resident'&&<label>Phase<select value={phase} onChange={e=>setPhase(e.target.value)}><option value="">All phases</option>{phases.map(p=><option key={p}>{p}</option>)}</select></label>}{role==='guard'&&<label>Gate assignment<select value={gateAssignment} onChange={e=>setGateAssignment(e.target.value)}><option value="">All gate assignments</option><option>Entry</option><option>Exit</option><option>Entry / Exit</option></select></label>}<label>Account<select value={userId} onChange={e=>setUserId(e.target.value)} required><option value="">Select account</option>{list.map(a=><option key={a.id} value={a.id}>{a.full_name} — {a.email}{a.gate_assignment?` — ${a.gate_assignment}`:''}</option>)}</select></label><label>Vehicle type<select value={f.vehicle_type} onChange={e=>setF({...f,vehicle_type:e.target.value})}>{['car','motorcycle','truck','tricycle','ebike','other'].map(x=><option key={x}>{x.toUpperCase()}</option>)}</select></label>{f.vehicle_type==='ebike'?<div className="alert">E-bike plate will be generated automatically from the account username and Phase/Block/Lot/Letter.</div>:<label>Plate number<input value={f.plate_number} onChange={e=>setF({...f,plate_number:e.target.value})} placeholder="XXX 1111" required/></label>}<label>Color<input value={f.color} onChange={e=>setF({...f,color:e.target.value})} required/></label><div className="button-row"><button type="button" className="secondary" onClick={onClose}>Cancel</button><button className="primary" disabled={busy||!userId}>{busy?'Saving…':'Add vehicle'}</button></div></form></Modal>
}
function AdminAccounts(){
    const [data,setData]=useState({residents:[],staff:[]}),[manage,setManage]=useState(null),[error,setError]=useState('');
    async function load(){try{const r=await api.get('/admin/accounts');if(r.data?.ok)setData(r.data)}catch(e){setError(e.response?.data?.message||'Unable to load accounts.')}}
    useEffect(()=>{load()},[]);
    return <section className="panel"><PanelHead title="Accounts" subtitle="Residents and staff are managed separately."/><p className="muted">Resident accounts are sortable by Phase, then Block, Lot and Letter. Online means recent account activity.</p>{error&&<div className="alert error">{error}</div>}<h3 className="section-title">Resident accounts</h3><div className="table-wrap"><table><thead><tr><th>Name</th><th>Phase</th><th>Block-Lot-Letter</th><th>Email</th><th>Online</th><th>Manage</th></tr></thead><tbody>{data.residents.map(u=><tr key={u.id}><td>{u.full_name}</td><td>{u.phase||'—'}</td><td>{[u.block_number,u.lot_number,u.household_letter].filter(Boolean).join('-')||u.house_number}</td><td>{u.email}</td><td><span className={`badge ${u.online?'online':'offline'}`}>{u.online?'Online':'Offline'}</span></td><td><button className="secondary compact" onClick={()=>setManage(u)}>Manage</button></td></tr>)}</tbody></table></div><h3 className="section-title">Staff accounts</h3><div className="table-wrap"><table><thead><tr><th>Name</th><th>Role</th><th>Gate assignment</th><th>Email</th><th>Online</th><th>Manage</th></tr></thead><tbody>{data.staff.map(u=><tr key={u.id}><td>{u.full_name}</td><td>{u.role}</td><td>{u.gate_assignment||'—'}</td><td>{u.email}</td><td><span className={`badge ${u.online?'online':'offline'}`}>{u.online?'Online':'Offline'}</span></td><td><button className="secondary compact" onClick={()=>setManage(u)}>Manage</button></td></tr>)}</tbody></table></div>{manage&&<AccountManageModal account={manage} onClose={()=>setManage(null)} onDone={()=>{setManage(null);load()}}/>}</section>
}
function AccountManageModal({account,onClose,onDone}){
    const [action,setAction]=useState('password'),[value,setValue]=useState(''),[busy,setBusy]=useState(false),[error,setError]=useState('');
    async function submit(){setBusy(true);setError('');try{if(action==='delete'){if(!window.confirm(`Delete ${account.full_name}? This cannot be undone.`))return;const r=await api.post(`/admin/accounts/${account.id}/delete`);if(r.status>=300||!r.data?.ok)throw new Error(r.data?.message||'Delete failed.')}else{const r=await api.post(`/admin/accounts/${account.id}/${action}`,action==='email'?{email:value}:{password:value});if(r.status>=300||!r.data?.ok)throw new Error(r.data?.message||'Update failed.')}onDone()}catch(e){setError(e.response?.data?.message||e.message)}finally{setBusy(false)}}
    return <Modal title={`Manage ${account.full_name}`} onClose={onClose}><div className="segmented"><button className={action==='password'?'selected':''} onClick={()=>setAction('password')}>Change password</button><button className={action==='email'?'selected':''} onClick={()=>setAction('email')}>Change email</button><button className={action==='delete'?'selected danger-selected':''} onClick={()=>setAction('delete')}>Delete</button></div>{error&&<div className="alert error">{error}</div>}{action!=='delete'&&<label>{action==='email'?'New email':'New password'}<input type={action==='password'?'password':'email'} value={value} onChange={e=>setValue(e.target.value)} minLength={action==='password'?8:undefined} required/></label>}<div className="button-row"><button className="secondary" onClick={onClose}>Cancel</button><button className={action==='delete'?'danger':'primary'} disabled={busy} onClick={submit}>{action==='delete'?'Delete account':busy?'Saving…':'Save'}</button></div></Modal>
}
function AdminRfid({cards}){return <section className="panel"><PanelHead title="RFID cards" subtitle="Registered RFID credentials used by the gate."/><div className="table-wrap"><table><thead><tr><th>UID</th><th>Account</th><th>Plate</th><th>Status</th></tr></thead><tbody>{cards.map(c=><tr key={c.id}><td>{c.uid||c.credential_code||'—'}</td><td>{c.full_name||c.email||'—'}</td><td>{c.plate_number||'—'}</td><td><span className={`badge ${c.status}`}>{c.status}</span></td></tr>)}</tbody></table></div></section>}
function AccountLogTable({logs}){return <div className="table-wrap"><table><thead><tr><th>Time (GMT+8)</th><th>Account</th><th>Role</th><th>Action</th><th>Details</th></tr></thead><tbody>{logs.length?logs.map(x=><tr key={x.id}><td>{manilaTime(x.created_at)}</td><td>{x.full_name||x.account_identifier||x.email}</td><td>{x.role||x.account_type}</td><td>{String(x.action).replaceAll('_',' ')}</td><td>{x.details||'—'}</td></tr>):<tr><td colSpan="5" className="empty">No account activity.</td></tr>}</tbody></table></div>}

function ResidentVehicleModal({onClose,onDone}){const [f,setF]=useState({plate_number:'',vehicle_type:'car',color:''}),[error,setError]=useState(''),[busy,setBusy]=useState(false);async function submit(e){e.preventDefault();setBusy(true);try{const payload={vehicle_type:f.vehicle_type,color:f.color};if(f.vehicle_type!=='ebike')payload.plate_number=f.plate_number.toUpperCase();const r=await api.post('/vehicles',payload);if(r.status>=300)throw new Error(r.data?.message||'Unable to add vehicle.');onDone()}catch(e){setError(e.response?.data?.message||e.message)}finally{setBusy(false)}}return <Modal title="Add vehicle" onClose={onClose}><p className="muted">Residents may register up to 20 vehicles.</p>{error&&<div className="alert error">{error}</div>}<form className="form-grid" onSubmit={submit}><label>Vehicle type<select value={f.vehicle_type} onChange={e=>setF({...f,vehicle_type:e.target.value})}>{['car','motorcycle','truck','tricycle','ebike','other'].map(x=><option key={x}>{x.toUpperCase()}</option>)}</select></label>{f.vehicle_type!=='ebike'&&<label>Plate number<input value={f.plate_number} onChange={e=>setF({...f,plate_number:e.target.value.toUpperCase()})} placeholder="XXX 1111" required/></label>}<label>Color<input value={f.color} onChange={e=>setF({...f,color:e.target.value})} required/></label>{f.vehicle_type==='ebike'&&<div className="alert full-width">Your e-bike plate will be generated automatically from your username and Phase/Block/Lot/Letter.</div>}<div className="full-width button-row"><button className="secondary" type="button" onClick={onClose}>Cancel</button><button className="primary" disabled={busy}>{busy?'Saving…':'Add vehicle'}</button></div></form></Modal>}
function ResidentRequests({requests}){return <section className="panel"><PanelHead title="Registered visitors" subtitle="Visitors you pre-register are automatically approved."/><div className="table-wrap"><table><thead><tr><th>Visitor</th><th>Credential</th><th>Visit vehicles</th><th>Status</th></tr></thead><tbody>{requests.length?requests.map(x=><tr key={x.id}><td>{x.visitor_name}</td><td>{x.visitor_id||'—'}</td><td>{x.vehicle_count||1}</td><td><span className={`badge ${x.status}`}>{x.status}</span></td></tr>):<tr><td colSpan="4" className="empty">No pre-registered visitors.</td></tr>}</tbody></table></div></section>}

function PreRegisterVisitorModal({onClose,onDone}){
    const [f,setF]=useState({visitor_name:'',contact_number:'',purpose_of_visit:'',people_count:1,vehicles:[{plate_number:'',vehicle_type:'car'}]}),[idFile,setIdFile]=useState(null),[error,setError]=useState(''),[busy,setBusy]=useState(false),[result,setResult]=useState(null);
    const updateVehicle=(i,k,v)=>setF(x=>({...x,vehicles:x.vehicles.map((a,n)=>n===i?{...a,[k]:v}:a)}));
    function addVehicle(){if(f.vehicles.length<10)setF(x=>({...x,vehicles:[...x.vehicles,{plate_number:'',vehicle_type:'car'}]}))}
    function removeVehicle(i){setF(x=>({...x,vehicles:x.vehicles.filter((_,n)=>n!==i)}))}
    async function submit(e){e.preventDefault();setBusy(true);setError('');try{const fd=new FormData();fd.append('visitor_name',f.visitor_name);fd.append('contact_number',f.contact_number);fd.append('purpose_of_visit',f.purpose_of_visit);fd.append('people_count',String(f.people_count));f.vehicles.forEach((v,i)=>{fd.append(`vehicles[${i}][plate_number]`,v.plate_number.toUpperCase());fd.append(`vehicles[${i}][vehicle_type]`,v.vehicle_type)});if(idFile)fd.append('government_id',idFile);const r=await api.post('/resident/visitors',fd);if(r.status>=300||!r.data?.ok)throw new Error(r.data?.message||'Unable to pre-register visitor.');setResult(r.data);onDone(r.data)}catch(e){setError(e.response?.data?.message||e.message)}finally{setBusy(false)}}
    if(result)return <Modal title="Visitor registered" onClose={onClose}><p className="muted">Automatically approved. Give the visitor the six-character code and barcode.</p><div className="credential"><span>Six-character code</span><strong>{result.visitor_id}</strong><Barcode value={result.visitor_id}/></div><button className="primary btn-block" onClick={onClose}>Done</button></Modal>;
    return <Modal title="Pre-register visitor" onClose={onClose} wide><p className="muted">You are registering this visitor yourself, so approval is automatic.</p>{error&&<div className="alert error">{error}</div>}<form className="form-grid" onSubmit={submit}><label>Visitor name<input value={f.visitor_name} onChange={e=>setF({...f,visitor_name:e.target.value})} required/></label><label>Contact number<input value={f.contact_number} onChange={e=>setF({...f,contact_number:e.target.value})}/></label><label>Purpose<input value={f.purpose_of_visit} onChange={e=>setF({...f,purpose_of_visit:e.target.value})} required/></label><label>People count<input type="number" min="1" max="20" value={f.people_count} onChange={e=>setF({...f,people_count:Number(e.target.value)})}/></label><div className="full-width"><div className="panel-head"><div><h3>Vehicles</h3><p className="muted">Up to 10 vehicles.</p></div><button type="button" className="secondary compact" onClick={addVehicle} disabled={f.vehicles.length>=10}>Add vehicle</button></div>{f.vehicles.map((v,i)=><div className="vehicle-row" key={i}><input placeholder="Plate number" value={v.plate_number} onChange={e=>updateVehicle(i,'plate_number',e.target.value.toUpperCase())} required/><select value={v.vehicle_type} onChange={e=>updateVehicle(i,'vehicle_type',e.target.value)}><option>car</option><option>motorcycle</option><option>truck</option><option>tricycle</option><option>ebike</option><option>other</option></select>{i>0&&<button type="button" className="text-button" onClick={()=>removeVehicle(i)}>Remove</button>}</div>)}</div><label className="full-width">Government ID (optional)<input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={e=>setIdFile(e.target.files?.[0]||null)}/></label><div className="full-width button-row"><button type="button" className="secondary" onClick={onClose}>Cancel</button><button className="primary" disabled={busy}>{busy?'Registering…':'Register visitor'}</button></div></form></Modal>
}

function ResidentDashboard({user,onLogout}) {
    const [active,setActive]=useState('overview'),[collapsed,setCollapsed]=useState(() => localStorage.getItem('smart-gate-sidebar') === 'mini'),[vehicles,setVehicles]=useState([]),[requests,setRequests]=useState([]),[error,setError]=useState(''),[modal,setModal]=useState(null);
    async function load(){try{const [v,r]=await Promise.all([api.get('/vehicles'),api.get('/resident/visitor-requests')]);if(v.status<300)setVehicles(v.data.vehicles||v.data||[]);if(r.status<300)setRequests(r.data.requests||[])}catch(e){setError(e.response?.data?.message||'Unable to load resident data.')}}
    useEffect(()=>{load()},[]);async function logout(){try{await api.post('/auth/logout')}finally{onLogout()}}
    const nav=navFor(user.role);
    return <Shell dashboard user={user} onHome={()=>setActive('overview')} onLogout={logout}><Sidebar user={user} active={active} setActive={setActive} collapsed={collapsed} setCollapsed={setCollapsed}/><main className={`dashboard-page ${collapsed ? 'sidebar-collapsed' : ''}`}><div className="mobile-sticky-nav">{nav.map(i=><button key={i.id} className={active===i.id?'active':''} onClick={()=>setActive(i.id)}><span>{i.icon}</span><small>{i.label}</small></button>)}</div><div className="dashboard-content">{error&&<div className="alert error">{error}</div>}{active==='overview'?<><section className="dashboard-head"><div><p className="eyebrow">RESIDENT DASHBOARD</p><h1>Welcome, {user.full_name}</h1><p className="muted">{user.email}</p></div></section><div className="stats"><Stat label="My vehicles" value={vehicles.length}/><Stat label="Registered visitors" value={requests.filter(r=>r.status==='approved').length}/></div><ResidentRequests requests={requests.filter(r=>r.status==='approved')}/><section className="feature-grid"><FeatureCard title="Pre-register visitor" text="Register a visitor yourself. Approval is automatic and you receive a barcode plus six-character code." button="Register visitor" onClick={()=>setModal('pre-register')}/></section></>:active==='vehicles'?<section className="panel"><PanelHead title="My vehicles" subtitle="Vehicles associated with your resident account." action={<button className="primary compact" onClick={()=>setModal('vehicle')}>Add vehicle</button>}/><div className="table-wrap"><table><thead><tr><th>Plate</th><th>Type</th><th>Color</th><th>Status</th><th></th></tr></thead><tbody>{vehicles.map(v=><tr key={v.id}><td>{v.plate_number}</td><td>{v.vehicle_type}</td><td>{v.color||'—'}</td><td>{v.status||'active'}</td><td><button className="text-button" onClick={async()=>{await api.delete(`/vehicles/${v.id}`);load()}}>Remove</button></td></tr>)}</tbody></table></div></section>:<ResidentRequests requests={requests.filter(r=>r.status==='approved')}/>}</div></main>{modal==='vehicle'&&<ResidentVehicleModal onClose={()=>setModal(null)} onDone={()=>{setModal(null);load()}}/>}{modal==='pre-register'&&<PreRegisterVisitorModal onClose={()=>{setModal(null);load()}} onDone={()=>{load()}}/>}</Shell>
}

function App(){
    const [view,setView]=useState('home'),[user,setUser]=useState(null);
    useEffect(()=>{api.get('/auth/me').then(r=>{if(r.status===200&&r.data?.user){setUser(r.data.user);setView('dashboard')}}).catch(()=>{})},[]);
    function navigate(next){setView(next);window.history.replaceState({},'',next==='home'?'/':`/${next}`)}
    function loggedIn(u){setUser(u);setView('dashboard');window.history.replaceState({},'','/dashboard')}
    function loggedOut(){setUser(null);navigate('home')}
    if(user) return user.role==='resident'?<ResidentDashboard user={user} onLogout={loggedOut}/>:<StaffDashboard user={user} onLogout={loggedOut}/>;
    if(view==='login') return <Login navigate={navigate} onLogin={loggedIn}/>;
    if(view==='visitor') return <Visitor navigate={navigate}/>;
    if(view==='status') return <VisitorStatus navigate={navigate}/>;
    return <Landing navigate={navigate}/>;
}

createRoot(document.getElementById('root')).render(<App />);
