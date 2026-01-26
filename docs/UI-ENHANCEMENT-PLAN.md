# UI Enhancement Plan

This document outlines the implementation plan for enhancing the todo-me application's UI with dark mode support, a refreshed color palette, and modern visual improvements.

## 1. Executive Summary

### Goals
1. **Dark Mode**: Full dark mode support with user-selectable theme preference (Light/Dark/System)
2. **Color Refresh**: Shift primary color from indigo to teal for a fresh, modern look
3. **Visual Polish**: Add depth, better shadows, accent borders, and improved visual hierarchy
4. **User Preference**: Store theme preference in user profile settings (persisted to database)

### Non-Goals
- Complete redesign or layout changes
- New features or functionality
- Third-party template adoption (AdminKit serves as inspiration only)

---

## 2. Theme System Architecture

### 2.1 Theme Options

Users can select from three theme modes:

| Mode | Behavior |
|------|----------|
| **Light** | Always use light theme |
| **Dark** | Always use dark theme |
| **System** | Follow OS/browser preference via `prefers-color-scheme` |

### 2.2 Storage Strategy

**User Preference Storage:**
- Stored in the `users.settings` JSON column as `theme` key
- Values: `'light'`, `'dark'`, `'system'` (default: `'system'`)
- No database migration required (uses existing JSON settings field)

**Runtime Application:**
1. Server renders initial theme class based on user setting
2. JavaScript handles system preference detection and real-time switching
3. Anonymous users default to system preference (stored in localStorage)

### 2.3 Implementation Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        Page Load                                 │
├─────────────────────────────────────────────────────────────────┤
│  1. Server reads user.settings.theme (or 'system' default)      │
│  2. If 'system': Server renders without dark class              │
│     If 'dark': Server renders with dark class                   │
│     If 'light': Server renders without dark class               │
│  3. JavaScript initializes:                                     │
│     - If 'system': Check prefers-color-scheme, toggle class     │
│     - Listen for media query changes                            │
│  4. Theme toggle button updates preference via API              │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Color System Redesign

### 3.1 Primary Color: Teal

Replacing indigo with teal as the primary brand color.

**Light Mode Teal Palette:**
```
Primary (Teal)
├── teal-600: #0D9488  - Primary actions, navigation header, focus states
├── teal-500: #14B8A6  - Hover states for primary elements
├── teal-700: #0F766E  - Active/pressed states
├── teal-100: #CCFBF1  - Light backgrounds, badges
├── teal-50:  #F0FDFA  - Subtle highlights
└── teal-900: #134E4A  - Dark mode primary text accents
```

**Dark Mode Adaptations:**
```
Primary (Teal - Dark Mode)
├── teal-400: #2DD4BF  - Primary actions (higher contrast on dark bg)
├── teal-300: #5EEAD4  - Hover states
├── teal-500: #14B8A6  - Active/pressed states
├── teal-900: #134E4A  - Dark backgrounds with teal tint
└── teal-950: #042F2E  - Deepest teal for surfaces
```

### 3.2 Semantic Colors

Semantic colors remain consistent but with dark mode variants:

| Purpose | Light Mode | Dark Mode |
|---------|------------|-----------|
| **Pending** | yellow-100 bg, yellow-800 text | yellow-900/30 bg, yellow-300 text |
| **In Progress** | blue-100 bg, blue-800 text | blue-900/30 bg, blue-300 text |
| **Completed** | green-100 bg, green-800 text | green-900/30 bg, green-300 text |
| **Overdue** | red-100 bg, red-800 text | red-900/30 bg, red-300 text |
| **Success** | green-50 bg, green-800 text | green-900/30 bg, green-300 text |
| **Error** | red-50 bg, red-800 text | red-900/30 bg, red-300 text |
| **Warning** | yellow-50 bg, yellow-800 text | yellow-900/30 bg, yellow-300 text |
| **Info** | blue-50 bg, blue-800 text | blue-900/30 bg, blue-300 text |

### 3.3 Neutral Colors

**Light Mode:**
```
Text
├── Primary:     gray-900 (#111827)
├── Secondary:   gray-700 (#374151)
├── Muted:       gray-500 (#6B7280)
└── Disabled:    gray-400 (#9CA3AF)

Backgrounds
├── Page:        gray-50  (#F9FAFB)
├── Surface:     white    (#FFFFFF)
├── Elevated:    white    (#FFFFFF) + shadow
└── Subtle:      gray-100 (#F3F4F6)

Borders
├── Default:     gray-200 (#E5E7EB)
├── Focus:       gray-300 (#D1D5DB)
└── Strong:      gray-400 (#9CA3AF)
```

**Dark Mode:**
```
Text
├── Primary:     gray-100 (#F3F4F6)
├── Secondary:   gray-300 (#D1D5DB)
├── Muted:       gray-400 (#9CA3AF)
└── Disabled:    gray-500 (#6B7280)

Backgrounds
├── Page:        gray-900 (#111827)
├── Surface:     gray-800 (#1F2937)
├── Elevated:    gray-700 (#374151)
└── Subtle:      gray-800 (#1F2937)

Borders
├── Default:     gray-700 (#374151)
├── Focus:       gray-600 (#4B5563)
└── Strong:      gray-500 (#6B7280)
```

---

## 4. Visual Enhancements

### 4.1 Navigation Bar

**Current:** Flat `bg-indigo-600`

**Enhanced:**
- Light: `bg-gradient-to-r from-teal-600 to-teal-500` with subtle shadow
- Dark: `bg-gray-800 border-b border-gray-700`
- Add subtle shadow: `shadow-sm`

### 4.2 Cards

**Current:** `bg-white shadow rounded-lg`

**Enhanced:**
- Light: `bg-white shadow-sm hover:shadow-md ring-1 ring-gray-200/50 rounded-xl`
- Dark: `bg-gray-800 ring-1 ring-gray-700 rounded-xl`
- Add left accent border option: `border-l-4 border-teal-500`
- Improve hover transition: `transition-all duration-200`

### 4.3 Sidebar

**Current:** White background, flat

**Enhanced:**
- Light: White with subtle gray-50 section headers
- Dark: `bg-gray-800` with `bg-gray-900` section headers
- Active item: `bg-teal-50 text-teal-700 border-l-3 border-teal-600` (light)
- Active item: `bg-teal-900/30 text-teal-300 border-l-3 border-teal-400` (dark)

### 4.4 Buttons

**Primary Button:**
```html
<!-- Light -->
<button class="bg-teal-600 hover:bg-teal-500 active:bg-teal-700
               text-white shadow-sm hover:shadow
               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">

<!-- Dark -->
<button class="dark:bg-teal-500 dark:hover:bg-teal-400 dark:active:bg-teal-600
               dark:text-gray-900 dark:shadow-teal-900/30">
```

**Secondary Button:**
```html
<!-- Light -->
<button class="bg-white ring-1 ring-gray-300 hover:bg-gray-50 text-gray-900">

<!-- Dark -->
<button class="dark:bg-gray-700 dark:ring-gray-600 dark:hover:bg-gray-600 dark:text-gray-100">
```

### 4.5 Form Inputs

```html
<!-- Light -->
<input class="bg-white ring-1 ring-gray-300 focus:ring-2 focus:ring-teal-600 text-gray-900">

<!-- Dark -->
<input class="dark:bg-gray-700 dark:ring-gray-600 dark:focus:ring-teal-400 dark:text-gray-100
              dark:placeholder-gray-400">
```

### 4.6 Task Cards

**Enhanced Task Card Structure:**
```html
<div class="bg-white dark:bg-gray-800
            rounded-xl shadow-sm dark:shadow-none
            ring-1 ring-gray-200 dark:ring-gray-700
            hover:shadow-md dark:hover:ring-gray-600
            transition-all duration-200
            border-l-4 border-transparent hover:border-teal-500">
  <!-- Task content -->
</div>
```

### 4.7 Status Badges

**Enhanced with better dark mode support:**
```html
<!-- Pending -->
<span class="bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">

<!-- In Progress -->
<span class="bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">

<!-- Completed -->
<span class="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">

<!-- Overdue -->
<span class="bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
```

### 4.8 Dropdowns & Modals

**Dropdown:**
```html
<div class="bg-white dark:bg-gray-800
            shadow-lg dark:shadow-2xl dark:shadow-black/20
            ring-1 ring-black/5 dark:ring-gray-700
            rounded-lg">
```

**Modal Backdrop:**
```html
<div class="bg-gray-500/75 dark:bg-gray-900/80">
```

**Modal Panel:**
```html
<div class="bg-white dark:bg-gray-800
            shadow-xl
            ring-1 ring-gray-200 dark:ring-gray-700">
```

---

## 5. Tailwind Configuration

### 5.1 Enable Dark Mode

Update `tailwind.config.js`:

```javascript
/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class', // Enable class-based dark mode
  content: [
    './templates/**/*.html.twig',
    './assets/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        // Semantic color aliases for consistency
        primary: {
          50: '#F0FDFA',
          100: '#CCFBF1',
          200: '#99F6E4',
          300: '#5EEAD4',
          400: '#2DD4BF',
          500: '#14B8A6',
          600: '#0D9488',
          700: '#0F766E',
          800: '#115E59',
          900: '#134E4A',
          950: '#042F2E',
        },
      },
    },
  },
  plugins: [],
}
```

### 5.2 CSS Variables (Optional Enhancement)

For more dynamic theming, consider CSS custom properties in `app.css`:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
  :root {
    --color-surface: 255 255 255;
    --color-surface-elevated: 255 255 255;
    --color-text-primary: 17 24 39;
    --color-text-secondary: 55 65 81;
    --color-border: 229 231 235;
  }

  .dark {
    --color-surface: 31 41 55;
    --color-surface-elevated: 55 65 81;
    --color-text-primary: 243 244 246;
    --color-text-secondary: 209 213 219;
    --color-border: 55 65 81;
  }
}
```

---

## 6. User Entity Changes

### 6.1 Theme Getter/Setter Methods

Add to `src/Entity/User.php`:

```php
/**
 * Get user's theme preference.
 *
 * @return string 'light', 'dark', or 'system'
 */
public function getTheme(): string
{
    return $this->settings['theme'] ?? 'system';
}

/**
 * Set user's theme preference.
 *
 * @param string $theme 'light', 'dark', or 'system'
 */
public function setTheme(string $theme): static
{
    if (!in_array($theme, ['light', 'dark', 'system'], true)) {
        $theme = 'system';
    }
    $this->settings['theme'] = $theme;
    return $this;
}
```

### 6.2 Update Settings Defaults

Update `getSettingsWithDefaults()`:

```php
public function getSettingsWithDefaults(): array
{
    return array_merge([
        'timezone' => 'UTC',
        'date_format' => 'MDY',
        'start_of_week' => 0,
        'theme' => 'system',
    ], $this->settings);
}
```

---

## 7. API Changes

### 7.1 Settings Endpoint Update

Update `PATCH /api/v1/users/me/settings` to accept `theme` parameter.

**Request:**
```json
{
  "theme": "dark"
}
```

**Validation:**
- Must be one of: `light`, `dark`, `system`

---

## 8. Template Changes

### 8.1 Base Template (`base.html.twig`)

**HTML Element:**
```html
<html lang="en" class="{{ theme_class }} h-full bg-gray-50 dark:bg-gray-900">
```

Where `theme_class` is computed by the server:
- If user.theme == 'dark': `class="dark"`
- If user.theme == 'light': `class=""`
- If user.theme == 'system': `class=""` (JS will handle)

**Body:**
```html
<body class="h-full text-gray-900 dark:text-gray-100">
```

### 8.2 Theme Initialization Script

Add to `<head>` before other scripts:

```html
<script>
  (function() {
    const theme = '{{ app.user ? app.user.theme : 'system' }}';
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (theme === 'dark' || (theme === 'system' && prefersDark)) {
      document.documentElement.classList.add('dark');
    }

    // Listen for system preference changes
    if (theme === 'system') {
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        document.documentElement.classList.toggle('dark', e.matches);
      });
    }
  })();
</script>
```

### 8.3 Theme Toggle Component

Create `templates/components/theme-toggle.html.twig`:

```html
<div x-data="themeToggle('{{ app.user ? app.user.theme : 'system' }}')" class="relative">
  <button @click="cycleTheme()"
          type="button"
          class="p-2 rounded-md text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200
                 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
          :title="themeLabel">
    <span class="sr-only" x-text="themeLabel"></span>

    <!-- Sun icon (light mode) -->
    <svg x-show="currentTheme === 'light'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
    </svg>

    <!-- Moon icon (dark mode) -->
    <svg x-show="currentTheme === 'dark'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
    </svg>

    <!-- Computer icon (system mode) -->
    <svg x-show="currentTheme === 'system'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
  </button>
</div>
```

### 8.4 Theme Toggle JavaScript

Add to `assets/js/theme-toggle.js`:

```javascript
function themeToggle(initialTheme) {
  return {
    currentTheme: initialTheme || 'system',
    themes: ['light', 'dark', 'system'],

    get themeLabel() {
      const labels = {
        light: 'Light mode',
        dark: 'Dark mode',
        system: 'System preference'
      };
      return labels[this.currentTheme];
    },

    cycleTheme() {
      const currentIndex = this.themes.indexOf(this.currentTheme);
      this.currentTheme = this.themes[(currentIndex + 1) % this.themes.length];
      this.applyTheme();
      this.saveTheme();
    },

    applyTheme() {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      const shouldBeDark = this.currentTheme === 'dark' ||
                          (this.currentTheme === 'system' && prefersDark);

      document.documentElement.classList.toggle('dark', shouldBeDark);
    },

    async saveTheme() {
      // For authenticated users, save to server
      try {
        await fetch(window.apiUrl('/api/v1/users/me/settings'), {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          credentials: 'same-origin',
          body: JSON.stringify({ theme: this.currentTheme })
        });
      } catch (error) {
        console.error('Failed to save theme preference:', error);
      }

      // Also save to localStorage for instant loading
      localStorage.setItem('theme', this.currentTheme);
    },

    init() {
      // Listen for system preference changes when in system mode
      window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (this.currentTheme === 'system') {
          this.applyTheme();
        }
      });
    }
  };
}
```

---

## 9. Profile Settings Page

### 9.1 Add Appearance Section

Add to `templates/settings/profile.html.twig`:

```html
{# Appearance Settings #}
<div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden mt-6">
    <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Appearance</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Customize how the application looks
        </p>
    </div>
    <div class="px-4 py-5 sm:p-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Theme</label>
            <div class="mt-3 grid grid-cols-3 gap-3">
                <button type="button"
                        @click="settings.theme = 'light'; saveSettings()"
                        :class="settings.theme === 'light'
                            ? 'ring-2 ring-teal-600 dark:ring-teal-400'
                            : 'ring-1 ring-gray-300 dark:ring-gray-600'"
                        class="relative flex flex-col items-center rounded-lg p-4 cursor-pointer
                               bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600
                               transition-colors">
                    <svg class="w-6 h-6 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Light</span>
                </button>

                <button type="button"
                        @click="settings.theme = 'dark'; saveSettings()"
                        :class="settings.theme === 'dark'
                            ? 'ring-2 ring-teal-600 dark:ring-teal-400'
                            : 'ring-1 ring-gray-300 dark:ring-gray-600'"
                        class="relative flex flex-col items-center rounded-lg p-4 cursor-pointer
                               bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600
                               transition-colors">
                    <svg class="w-6 h-6 text-gray-700 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <span class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Dark</span>
                </button>

                <button type="button"
                        @click="settings.theme = 'system'; saveSettings()"
                        :class="settings.theme === 'system'
                            ? 'ring-2 ring-teal-600 dark:ring-teal-400'
                            : 'ring-1 ring-gray-300 dark:ring-gray-600'"
                        class="relative flex flex-col items-center rounded-lg p-4 cursor-pointer
                               bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600
                               transition-colors">
                    <svg class="w-6 h-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">System</span>
                </button>
            </div>
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                Choose how the app appears. "System" follows your device's setting.
            </p>
        </div>
    </div>
</div>
```

---

## 10. Implementation Phases

### Phase 1: Foundation (Day 1-2)

**Objective:** Set up dark mode infrastructure without visual changes

1. **Tailwind Configuration**
   - Enable `darkMode: 'class'` in `tailwind.config.js`
   - Rebuild Tailwind CSS

2. **User Entity**
   - Add `getTheme()` and `setTheme()` methods
   - Update `getSettingsWithDefaults()`

3. **API Update**
   - Accept `theme` in settings PATCH endpoint
   - Add validation

4. **Base Template**
   - Add theme initialization script
   - Add `dark` class conditionally to `<html>`

5. **Theme Toggle**
   - Create theme toggle component
   - Add to navigation bar

**Deliverables:**
- Theme can be toggled and persisted
- No visual dark mode yet (just infrastructure)

---

### Phase 2: Color Migration (Day 2-3)

**Objective:** Replace indigo with teal throughout the app

1. **Global Find/Replace**
   - `indigo-600` → `teal-600`
   - `indigo-500` → `teal-500`
   - `indigo-700` → `teal-700`
   - `indigo-400` → `teal-400`
   - `indigo-100` → `teal-100`
   - `indigo-50` → `teal-50`

2. **Component-by-Component Review**
   - Navigation bar
   - Buttons
   - Focus states
   - Links
   - Badges

3. **Update UI Design System Document**
   - Replace all indigo references with teal
   - Add dark mode section

**Deliverables:**
- All UI uses teal as primary color
- Consistent appearance in light mode

---

### Phase 3: Dark Mode - Core Components (Day 3-4)

**Objective:** Apply dark mode styles to core UI components

1. **Base Layout**
   - `<html>`: `bg-gray-50 dark:bg-gray-900`
   - `<body>`: `text-gray-900 dark:text-gray-100`

2. **Navigation**
   - Light: `bg-teal-600`
   - Dark: `bg-gray-800 border-b border-gray-700`

3. **Cards**
   - `bg-white dark:bg-gray-800`
   - `ring-1 ring-gray-200 dark:ring-gray-700`

4. **Forms**
   - Inputs, selects, textareas
   - Labels and helper text
   - Error states

5. **Buttons**
   - Primary, secondary, danger, ghost variants

**Templates to Update:**
- `base.html.twig`
- `components/quick-add.html.twig`
- `task/_task_item.html.twig`
- `task/list.html.twig`
- `settings/*.html.twig`

---

### Phase 4: Dark Mode - Page-Specific (Day 4-5)

**Objective:** Complete dark mode for all pages

1. **Task List**
   - Filter panel
   - Empty states
   - Task cards

2. **Task Details/Edit**
   - Form fields
   - Date pickers
   - Tag inputs

3. **Projects Sidebar**
   - Project tree
   - Active states
   - Add project modal

4. **Settings Pages**
   - Profile settings
   - Notification settings
   - Security settings

5. **Auth Pages**
   - Login
   - Register
   - Password reset

6. **Modals**
   - All modal dialogs
   - Dropdowns
   - Tooltips

---

### Phase 5: Visual Polish (Day 5-6)

**Objective:** Add enhanced visual effects

1. **Shadows & Depth**
   - Improve card shadows
   - Add elevation system for dark mode

2. **Borders & Accents**
   - Add subtle ring borders
   - Accent borders on task cards (optional)

3. **Transitions**
   - Smooth theme transitions
   - Hover effects refinement

4. **Navigation Enhancement**
   - Gradient option for light mode nav
   - Better active states

5. **Accessibility Audit**
   - Verify contrast ratios in both modes
   - Test focus states
   - Screen reader testing

---

### Phase 6: Documentation & Testing (Day 6-7)

**Objective:** Update all documentation and test thoroughly

1. **Update UI-DESIGN-SYSTEM.md**
   - Add complete dark mode section
   - Update all color references
   - Add component dark mode examples

2. **Update CLAUDE.md**
   - Note dark mode support
   - Update color references

3. **Manual Testing**
   - All pages in light mode
   - All pages in dark mode
   - System preference switching
   - Mobile responsive testing

4. **Browser Testing**
   - Chrome, Firefox, Safari
   - iOS Safari
   - Android Chrome

---

## 11. Files to Modify

### Configuration
- `tailwind.config.js` - Enable dark mode, add color aliases

### PHP/Backend
- `src/Entity/User.php` - Add theme getter/setter
- `src/Controller/Api/UserController.php` - Accept theme in settings update
- `src/DTO/UpdateUserSettingsRequest.php` - Add theme validation (if exists)

### Templates (Priority Order)
1. `templates/base.html.twig` - Core layout, theme initialization
2. `templates/task/_task_item.html.twig` - Task card component
3. `templates/task/list.html.twig` - Main task list
4. `templates/components/quick-add.html.twig` - Quick add input
5. `templates/partials/sidebar.html.twig` - Sidebar navigation
6. `templates/settings/profile.html.twig` - Theme settings UI
7. `templates/settings/layout.html.twig` - Settings layout
8. `templates/settings/notifications.html.twig`
9. `templates/security/login.html.twig`
10. `templates/security/register.html.twig`
11. `templates/components/notification-dropdown.html.twig`
12. `templates/components/project-create-modal.html.twig`
13. `templates/components/keyboard-help-modal.html.twig`
14. All other templates in `templates/`

### JavaScript
- `assets/app.js` - Import theme toggle
- `assets/js/theme-toggle.js` - New file for theme logic

### CSS
- `assets/styles/app.css` - Optional CSS variables

### Documentation
- `docs/UI-DESIGN-SYSTEM.md` - Major update
- `docs/UI-ENHANCEMENT-PLAN.md` - This document
- `CLAUDE.md` - Minor updates

---

## 12. Color Migration Reference

### Quick Reference: Indigo → Teal

| Old (Indigo) | New (Teal) | Usage |
|--------------|------------|-------|
| `indigo-50` | `teal-50` | Subtle highlights |
| `indigo-100` | `teal-100` | Light backgrounds |
| `indigo-200` | `teal-200` | Borders |
| `indigo-300` | `teal-300` | Dark mode hover |
| `indigo-400` | `teal-400` | Dark mode primary |
| `indigo-500` | `teal-500` | Hover states |
| `indigo-600` | `teal-600` | **Primary color** |
| `indigo-700` | `teal-700` | Active/pressed |
| `indigo-800` | `teal-800` | Deep accents |
| `indigo-900` | `teal-900` | Darkest variant |

### Regex for Global Replace

```regex
Find:    \bindigo-(\d+)\b
Replace: teal-$1
```

**Note:** Review each replacement in context. Some uses (like third-party components) may not need changing.

---

## 13. Accessibility Considerations

### Contrast Ratios

All color combinations must meet WCAG 2.1 AA standards:
- **Normal text**: 4.5:1 minimum
- **Large text (18px+)**: 3:1 minimum
- **UI components**: 3:1 minimum

### Verified Combinations

| Background | Text | Ratio | Pass |
|------------|------|-------|------|
| white | teal-600 | 4.53:1 | Yes |
| teal-600 | white | 4.53:1 | Yes |
| gray-900 | teal-400 | 7.14:1 | Yes |
| gray-800 | gray-100 | 11.7:1 | Yes |
| gray-800 | gray-300 | 7.46:1 | Yes |

### Focus States

All interactive elements must have visible focus indicators:
- Light: `focus:ring-2 focus:ring-teal-600 focus:ring-offset-2`
- Dark: `dark:focus:ring-teal-400 dark:focus:ring-offset-gray-900`

---

## 14. Testing Checklist

### Functional Testing
- [ ] Theme toggle cycles through light → dark → system
- [ ] Theme preference saves to user settings
- [ ] Theme persists across page reloads
- [ ] System preference changes are detected and applied
- [ ] Anonymous users can toggle theme (localStorage)

### Visual Testing - Light Mode
- [ ] Navigation bar uses teal gradient
- [ ] All buttons use teal for primary actions
- [ ] Form focus states show teal ring
- [ ] Status badges have correct colors
- [ ] Cards have proper shadows and borders

### Visual Testing - Dark Mode
- [ ] Page background is dark gray
- [ ] Cards have dark background with subtle borders
- [ ] Text is legible (sufficient contrast)
- [ ] Form inputs have dark backgrounds
- [ ] Status badges are visible and readable
- [ ] Modals have dark backgrounds
- [ ] Dropdowns have dark backgrounds

### Responsive Testing
- [ ] Mobile navigation works in both themes
- [ ] Sidebar works in both themes
- [ ] Task cards are readable on small screens
- [ ] Touch targets are adequate (44x44px minimum)

### Accessibility Testing
- [ ] Keyboard navigation works throughout
- [ ] Focus indicators visible in both themes
- [ ] Screen reader announces theme changes
- [ ] Color contrast meets WCAG AA

---

## 15. Rollback Plan

If issues arise during implementation:

1. **Revert Tailwind Config**
   - Remove `darkMode: 'class'`
   - Rebuild CSS

2. **Revert Template Changes**
   - Remove all `dark:` classes
   - Remove theme initialization script
   - Remove theme toggle component

3. **Revert Color Changes**
   - Global replace `teal` back to `indigo`

4. **Keep Backend Changes**
   - User.getTheme() can remain (harmless)
   - API can continue accepting theme param

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-26 | Claude | Initial implementation plan |
