import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '../lib/api';
import type { AppNotification } from '../lib/types';

const POLL_INTERVAL_MS = 30_000;

export function TopNav() {
  const { token, user, clearAuth } = useAuth();
  const navigate = useNavigate();

  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [notifOpen, setNotifOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);

  const notifRef = useRef<HTMLDivElement>(null);
  const profileRef = useRef<HTMLDivElement>(null);

  async function loadNotifications() {
    if (!token) return;
    try {
      const res = await fetchNotifications(token);
      setNotifications(res.notifications);
      setUnreadCount(res.unread_count);
    } catch {
      // silently ignore — non-critical
    }
  }

  useEffect(() => {
    loadNotifications();
    const interval = setInterval(loadNotifications, POLL_INTERVAL_MS);
    return () => clearInterval(interval);
  }, [token]);

  // Close dropdowns on outside click
  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (notifRef.current && !notifRef.current.contains(e.target as Node)) {
        setNotifOpen(false);
      }
      if (profileRef.current && !profileRef.current.contains(e.target as Node)) {
        setProfileOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  async function handleMarkAllRead() {
    if (!token) return;
    await markAllNotificationsRead(token);
    setNotifications((prev) => prev.map((n) => ({ ...n, read_at: new Date().toISOString() })));
    setUnreadCount(0);
  }

  async function handleNotifClick(notif: AppNotification) {
    if (!token) return;
    if (!notif.read_at) {
      await markNotificationRead(token, notif.id);
      setNotifications((prev) =>
        prev.map((n) => (n.id === notif.id ? { ...n, read_at: new Date().toISOString() } : n))
      );
      setUnreadCount((c) => Math.max(0, c - 1));
    }
    setNotifOpen(false);
    if (notif.project_id) {
      navigate(`/projects/${notif.project_id}`);
    }
  }

  function formatRelative(iso: string) {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60_000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
  }

  return (
    <nav className="top-nav">
      <Link to="/projects" className="top-nav-brand">
        WBT Forge
      </Link>

      <div className="top-nav-actions">
        {/* Notification bell */}
        <div className="top-nav-dropdown" ref={notifRef}>
          <button
            type="button"
            className="top-nav-icon-btn"
            onClick={() => { setNotifOpen((o) => !o); setProfileOpen(false); }}
            aria-label="Notifications"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" />
            </svg>
            {unreadCount > 0 && (
              <span className="top-nav-badge">{unreadCount > 9 ? '9+' : unreadCount}</span>
            )}
          </button>

          {notifOpen && (
            <div className="top-nav-dropdown-panel notif-panel">
              <div className="notif-panel-header">
                <span>Notifications</span>
                {unreadCount > 0 && (
                  <button type="button" className="notif-mark-all" onClick={handleMarkAllRead}>
                    Mark all read
                  </button>
                )}
              </div>
              {notifications.length === 0 ? (
                <p className="notif-empty">No notifications yet</p>
              ) : (
                <ul className="notif-list">
                  {notifications.map((n) => (
                    <li
                      key={n.id}
                      className={`notif-item${!n.read_at ? ' notif-unread' : ''}`}
                      onClick={() => handleNotifClick(n)}
                    >
                      <div className="notif-item-dot" />
                      <div className="notif-item-body">
                        <strong>{n.title}</strong>
                        <p>{n.body}</p>
                        <time>{formatRelative(n.created_at)}</time>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </div>

        {/* Profile menu */}
        <div className="top-nav-dropdown" ref={profileRef}>
          <button
            type="button"
            className="top-nav-icon-btn top-nav-avatar"
            onClick={() => { setProfileOpen((o) => !o); setNotifOpen(false); }}
            aria-label="Profile"
          >
            {user?.name?.charAt(0).toUpperCase() ?? user?.email?.charAt(0).toUpperCase() ?? '?'}
          </button>

          {profileOpen && (
            <div className="top-nav-dropdown-panel profile-panel">
              <div className="profile-panel-user">
                <strong>{user?.name}</strong>
                <span>{user?.email}</span>
              </div>
              <hr className="profile-panel-divider" />
              <button
                type="button"
                className="profile-panel-logout"
                onClick={() => { setProfileOpen(false); clearAuth(); }}
              >
                Sign out
              </button>
            </div>
          )}
        </div>
      </div>
    </nav>
  );
}
