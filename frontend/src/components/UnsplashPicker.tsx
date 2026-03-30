import { useEffect, useRef, useState } from 'react';
import { searchUnsplash, type UnsplashPhoto } from '../lib/api';

export type PickedImage = {
  url: string;
  alt_text: string;
  attribution: {
    image_id: string;
    photographer: string;
    url: string;
    license: string;
  };
};

type Props = {
  token: string;
  initialQuery?: string;
  onSelect: (image: PickedImage) => void;
  onClose: () => void;
};

export function UnsplashPicker({ token, initialQuery = '', onSelect, onClose }: Props) {
  const [query, setQuery] = useState(initialQuery);
  const [photos, setPhotos] = useState<UnsplashPhoto[]>([]);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Focus search input on open
  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  // Close on Escape
  useEffect(() => {
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') onClose();
    }
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [onClose]);

  // Debounced search when query or page changes
  useEffect(() => {
    if (!query.trim()) {
      setPhotos([]);
      setTotalPages(0);
      return;
    }
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      runSearch(query.trim(), page);
    }, 400);
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [query, page]);

  async function runSearch(q: string, p: number) {
    setLoading(true);
    setError(null);
    try {
      const res = await searchUnsplash(token, q, p);
      setPhotos(res.results);
      setTotalPages(res.total_pages);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Search failed');
    } finally {
      setLoading(false);
    }
  }

  function handleQueryChange(value: string) {
    setQuery(value);
    setPage(1);
  }

  function handleSelect(photo: UnsplashPhoto) {
    onSelect({
      url: photo.urls.regular,
      alt_text: photo.alt_description ?? photo.description ?? '',
      attribution: {
        image_id: photo.id,
        photographer: photo.user.name,
        url: photo.links.html,
        license: 'Unsplash',
      },
    });
  }

  return (
    <div className="picker-backdrop" onClick={e => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="picker-modal" role="dialog" aria-modal="true" aria-label="Search Unsplash images">
        <div className="picker-header">
          <span className="picker-title">Search images</span>
          <button type="button" className="ghost picker-close" onClick={onClose} aria-label="Close">
            ✕
          </button>
        </div>

        <div className="picker-search">
          <input
            ref={inputRef}
            type="search"
            placeholder="Search Unsplash…"
            value={query}
            onChange={e => handleQueryChange(e.target.value)}
            aria-label="Search Unsplash images"
          />
        </div>

        <div className="picker-body">
          {error && <p style={{ color: '#ef4444', padding: '0.5rem 0' }}>{error}</p>}

          {loading && <p className="muted picker-status">Searching…</p>}

          {!loading && query && photos.length === 0 && !error && (
            <p className="muted picker-status">No results for "{query}"</p>
          )}

          {!loading && !query && (
            <p className="muted picker-status">Type to search for images</p>
          )}

          {photos.length > 0 && (
            <div className="picker-grid">
              {photos.map(photo => (
                <button
                  key={photo.id}
                  type="button"
                  className="picker-photo"
                  onClick={() => handleSelect(photo)}
                  title={photo.alt_description ?? photo.user.name}
                  aria-label={`Select photo by ${photo.user.name}: ${photo.alt_description ?? ''}`}
                >
                  <img src={photo.urls.thumb} alt={photo.alt_description ?? ''} loading="lazy" />
                  <span className="picker-photo-credit">{photo.user.name}</span>
                </button>
              ))}
            </div>
          )}
        </div>

        {totalPages > 1 && (
          <div className="picker-footer">
            <button
              type="button"
              className="ghost"
              onClick={() => setPage(p => p - 1)}
              disabled={page <= 1}
            >
              ← Prev
            </button>
            <span className="muted">Page {page} of {totalPages}</span>
            <button
              type="button"
              className="ghost"
              onClick={() => setPage(p => p + 1)}
              disabled={page >= totalPages}
            >
              Next →
            </button>
          </div>
        )}

        <p className="picker-unsplash-credit">
          Photos from{' '}
          <a href="https://unsplash.com" target="_blank" rel="noopener noreferrer">
            Unsplash
          </a>
        </p>
      </div>
    </div>
  );
}
