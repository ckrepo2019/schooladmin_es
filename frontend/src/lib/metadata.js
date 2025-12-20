export function setFavicon(href) {
  if (typeof document === 'undefined') return
  if (!href) return

  const ensureLink = (rel) => {
    let link = document.querySelector(`link[rel="${rel}"]`)
    if (!link) {
      link = document.createElement('link')
      link.setAttribute('rel', rel)
      document.head.appendChild(link)
    }
    return link
  }

  for (const rel of ['icon', 'shortcut icon', 'apple-touch-icon']) {
    const link = ensureLink(rel)
    link.setAttribute('href', href)
    link.removeAttribute('type')
  }
}

export function cacheBustedHref(href, cacheKey) {
  if (!href) return href
  if (typeof window === 'undefined') return href

  try {
    const url = new URL(href, window.location.href)
    url.searchParams.set('v', String(cacheKey ?? Date.now()))
    return url.toString()
  } catch {
    return href
  }
}

