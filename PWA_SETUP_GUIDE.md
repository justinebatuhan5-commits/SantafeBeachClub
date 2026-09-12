# PWA Setup Guide - Santa Fe Beach Club

Your website is now configured as a **Progressive Web App (PWA)** with the "Install app" experience!

## ✅ What's Been Configured

### 1. **Web App Manifest** (`manifest.json`)
- App name, colors, icons, and shortcuts configured
- Theme color: Beach Sunset (#ff6b6b)
- Start URL: Admin Login page

### 2. **Service Worker** (`sw.js`)
- Updated to cache admin/staff pages for offline access
- Intelligent cache strategy:
  - Static assets cached on install
  - Admin/staff pages cached-on-use for offline fallback
  - Backend APIs never cached (always fresh)

### 3. **PWA Meta Tags** Added to:
- `admin_login.php`
- `staff_login.php`
- `admin_dashboard.php`

Includes:
- Manifest link
- Theme color
- Apple webapp meta tags
- Apple touch icon
- Description

### 4. **Install Prompt**
- Beautiful install UI that appears when PWA criteria are met
- User can install app or dismiss
- Automatically hides on app installation

## 📱 Install Experience

Users will see an **"Install App"** banner on:
- Chrome/Edge (Android & Desktop)
- Samsung Internet
- Firefox (Android)
- Safari (iOS) - Add to Home Screen button in share menu

**Click "Install"** → App gets added to home screen → Opens in standalone window

## 🎨 Icons Required

Create these PNG files in `frontend/assets/icons/`:

```
icon-192x192.png         (192×192 px) - For modern browsers
icon-512x512.png         (512×512 px) - For app store
icon-maskable-192x192.png (192×192 px) - For maskable icons
icon-maskable-512x512.png (512×512 px) - For maskable icons
```

**Maskable icons** (with safe zone) look better on adaptive icons. Use a design tool or:
- Make icons with transparent background
- Safe content in center 40% of canvas
- Design with rounded edges for better adaptive display

### Quick Icon Generation
```bash
# Using ImageMagick (if installed)
convert logo.jpg -resize 192x192 icon-192x192.png
convert logo.jpg -resize 512x512 icon-512x512.png
```

Or use online tools like:
- [Maskable.app](https://maskable.app)
- [PWA Image Generator](https://progressiveapp.com/)

## ✨ Features When Installed

### Admin & Staff Get:
- ✅ Standalone app window (no browser UI)
- ✅ App launcher icon
- ✅ Offline access to cached pages
- ✅ Custom splash screen
- ✅ Back button navigation
- ✅ Offline indicator message

### Security Notes:
- 🔒 Login pages still require network for MFA/OTP
- 🔒 All API calls bypass cache (always fresh auth)
- 🔒 Session tokens not cached locally

## 🔧 Testing

### Desktop (Chrome/Edge):
1. Open DevTools (F12)
2. Go to **Application → Manifest**
3. Look for "Install" button

### Mobile:
1. Open site on Android Chrome
2. Look for install banner at bottom/top
3. Tap "Install" or menu → "Install app"

## 📋 Offline Behavior

When offline:
- Cached pages load from service worker
- Shows "offline" message if page not cached
- API calls fail gracefully (user sees error)
- No data loss - page state preserved

## 🚀 Next Steps

1. **Add icons** to `frontend/assets/icons/`
2. **Test on mobile** (Android Chrome recommended first)
3. **Check manifest** in DevTools
4. **Verify Service Worker** registration in DevTools → Application → Service Workers

## 📝 Updating PWA

To update app name, colors, or start URL:
1. Edit `manifest.json`
2. Update theme-color in head tags if needed
3. Changes apply on next user visit/update

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| Install button not showing | Icons must be PNG, exact sizes, accessible at paths |
| Cache not working | Clear site data in DevTools → Clear storage |
| Offline page looks broken | Check that CSS assets are cached |
| "Cannot find manifest" | Verify path is `/frontend/manifest.json` |

---

**Your PWA is ready!** Users can now install your admin/staff portal as a native-like app. 🎉
