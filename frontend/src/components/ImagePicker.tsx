import { useEffect, useRef, useState } from 'react';
import {
  searchPexels, searchUnsplash,
  type PexelsPhoto, type UnsplashPhoto,
} from '../lib/api';

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

type Source = 'pexels' | 'unsplash';

type Props = {
  token: string;
  initialQuery?: string;
  onSelect: (image: PickedImage) => void;
  onClose: () => void;
};

export function ImagePicker({ token, initialQuery = '', onSelect, onClose }: Props) {
  const [source, setSource] = useState<Source>('pexels');
  const [query, setQuery]   = useState(initialQuery);
  const [page, setPage]     = useState(1);
  const [pexelsPhotos, setPexelsPhotos]     = useState<PexelsPhoto[]>([]);
  const [unsplashPhotos, setUnsplashPhotos] = useState<UnsplashPhoto[]>([]);
  const [totalPages, setTotalPages] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState<string | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const inputRef    = useRef<HTMLInputElement>(null);

  useEffect(() => { inputRef.current?.focus(); }, []);

  useEffect(() => {
    function onKey(e: KeyboardEvent) { if (e.key === 'Escape') onClose(); }
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  // Re-search whenever query, page, or source tab changes
  useEffect(() => {
    if (!query.trim()) {
      setPexelsPhotos([]);
      setUnsplashPhotos([]);
      setTotalPages(0);
      return;
    }
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => runSearch(query.trim(), page), 400);
    return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
  }, [query, page, source]);

  async function runSearch(q: string, p: number) {
    setLoading(true);
    setError(null);
    try {
      if (source === 'pexels') {
        const res = await searchPexels(token, q, p);
        setPexelsPhotos(res.photos);
        setTotalPages(res.total_pages);
      } else {
        const res = await searchUnsplash(token, q, p);
        setUnsplashPhotos(res.results);
        setTotalPages(res.total_pages);
      }
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

  function handleSourceChange(s: Source) {
    setSource(s);
    setPage(1);
  }

  function handleSelectPexels(photo: PexelsPhoto) {
    onSelect({
      url: photo.url,
      alt_text: photo.alt,
      attribution: {
        image_id: photo.id,
        photographer: photo.photographer,
        url: photo.photographer_url,
        license: 'Pexels License',
      },
    });
  }

  function handleSelectUnsplash(photo: UnsplashPhoto) {
    onSelect({
      url: photo.urls.regular,
      alt_text: photo.alt_description ?? photo.description ?? '',
      attribution: {
        image_id: photo.id,
        photographer: photo.user.name,
        url: photo.links.html,
        license: 'Unsplash License',
      },
    });
  }

  const photos = source === 'pexels' ? pexelsPhotos : unsplashPhotos;
  const hasResults = photos.length > 0;

  return (
    <div className="picker-backdrop" onClick={e => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="picker-modal" role="dialog" aria-modal="true" aria-label="Search images">
        <div className="picker-header">
          <span className="picker-title">Search images</span>
          <button type="button" className="ghost picker-close" onClick={onClose} aria-label="Close">✕</button>
        </div>

        <div className="picker-tabs">
          <button
            type="button"
            className={`picker-tab${source === 'pexels' ? ' active' : ''}`}
            onClick={() => handleSourceChange('pexels')}
          >
            Pexels
          </button>
          <button
            type="button"
            className={`picker-tab${source === 'unsplash' ? ' active' : ''}`}
            onClick={() => handleSourceChange('unsplash')}
          >
            Unsplash
          </button>
        </div>

        <div className="picker-search">
          <input
            ref={inputRef}
            type="search"
            placeholder={`Search ${source === 'pexels' ? 'Pexels' : 'Unsplash'}…`}
            value={query}
            onChange={e => handleQueryChange(e.target.value)}
            aria-label="Search images"
          />
        </div>

        <div className="picker-body">
          {error && <p style={{ color: '#ef4444', padding: '0.5rem 0' }}>{error}</p>}
          {loading && <p className="muted picker-status">Searching…</p>}
          {!loading && query && !hasResults && !error && (
            <p className="muted picker-status">No results for "{query}"</p>
          )}
          {!loading && !query && (
            <p className="muted picker-status">Type to search for images</p>
          )}

          {hasResults && source === 'pexels' && (
            <div className="picker-grid">
              {(pexelsPhotos).map(photo => (
                <button
                  key={photo.id}
                  type="button"
                  className="picker-photo"
                  onClick={() => handleSelectPexels(photo)}
                  title={photo.alt || photo.photographer}
                  aria-label={`Select photo by ${photo.photographer}`}
                >
                  <img src={photo.thumb} alt={photo.alt} loading="lazy" />
                  <span className="picker-photo-credit">{photo.photographer}</span>
                </button>
              ))}
            </div>
          )}

          {hasResults && source === 'unsplash' && (
            <div className="picker-grid">
              {(unsplashPhotos).map(photo => (
                <button
                  key={photo.id}
                  type="button"
                  className="picker-photo"
                  onClick={() => handleSelectUnsplash(photo)}
                  title={photo.alt_description ?? photo.user.name}
                  aria-label={`Select photo by ${photo.user.name}`}
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
            <button type="button" className="ghost" onClick={() => setPage(p => p - 1)} disabled={page <= 1}>
              ← Prev
            </button>
            <span className="muted">Page {page} of {totalPages}</span>
            <button type="button" className="ghost" onClick={() => setPage(p => p + 1)} disabled={page >= totalPages}>
              Next →
            </button>
          </div>
        )}

        <p className="picker-unsplash-credit">
          {source === 'pexels'
            ? <>Photos from <a href="https://www.pexels.com" target="_blank" rel="noopener noreferrer">Pexels</a></>
            : <>Photos from <a href="https://unsplash.com" target="_blank" rel="noopener noreferrer">Unsplash</a></>
          }
        </p>
      </div>
    </div>
  );
}
