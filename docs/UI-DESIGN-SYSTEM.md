# UI Design System

This document establishes the visual language and component guidelines for the todo-me application. It ensures a clean, crisp, modern interface that is consistent throughout the application.

## 1. Design Principles

### 1.1 Core Principles

**Clarity Over Cleverness**
- Every element serves a purpose
- Avoid decorative elements that don't aid comprehension
- Use familiar patterns that users recognize instantly

**Minimal Friction**
- Tasks should flow from thought to system with minimal effort
- Reduce cognitive load through progressive disclosure
- Optimize for the most common actions

**Consistent Visual Language**
- Repeatable patterns across all views
- Predictable placement of common elements
- Uniform spacing, typography, and interaction patterns

**Accessibility First**
- Meet WCAG 2.1 AA standards minimum
- Keyboard navigable throughout
- Sufficient color contrast (4.5:1 for text, 3:1 for UI components)

### 1.2 Design Hierarchy

1. **Content is King** - Task information is always the primary focus
2. **Actions are Secondary** - Buttons and controls support content, not compete with it
3. **Navigation is Tertiary** - Navigation should fade into the background until needed

---

## 2. Color System

### 2.1 Brand Colors

```
Primary (Teal)
├── teal-600: #0D9488  - Primary actions, navigation header, focus states
├── teal-500: #14B8A6  - Hover states for primary elements
├── teal-700: #0F766E  - Active/pressed states
├── teal-400: #2DD4BF  - Light accents
├── teal-100: #CCFBF1  - Light backgrounds, badges
└── teal-50:  #F0FDFA  - Subtle highlights
```

### 2.2 Semantic Colors

```
Status Colors
├── Pending:     yellow-400 (#FBBF24), yellow-100 bg, yellow-800 text
├── In Progress: blue-400   (#60A5FA), blue-100 bg, blue-800 text
├── Completed:   green-400  (#4ADE80), green-100 bg, green-800 text
└── Overdue:     red-500    (#EF4444), red-100 bg, red-800 text

Feedback Colors
├── Success:     green-500  (#22C55E)
├── Error:       red-500    (#EF4444)
├── Warning:     yellow-500 (#EAB308)
└── Info:        blue-500   (#3B82F6)
```

### 2.3 Neutral Colors

```
Text
├── Primary:     gray-900 (#111827) - Headings, important text
├── Secondary:   gray-700 (#374151) - Body text
├── Muted:       gray-500 (#6B7280) - Helper text, metadata
└── Disabled:    gray-400 (#9CA3AF) - Disabled states

Backgrounds
├── Page:        gray-50  (#F9FAFB) - Main page background
├── Surface:     white    (#FFFFFF) - Cards, panels, modals
├── Elevated:    white    (#FFFFFF) - Dropdowns, tooltips (with shadow)
└── Subtle:      gray-100 (#F3F4F6) - Hover backgrounds, dividers

Borders
├── Default:     gray-200 (#E5E7EB) - Card borders, dividers
├── Focus:       gray-300 (#D1D5DB) - Input borders
└── Strong:      gray-400 (#9CA3AF) - Emphasized separators
```

### 2.4 Priority Colors

```
Priority Indicator (Stars)
├── Active:      yellow-400 (#FBBF24) - Filled stars
├── Inactive:    gray-300   (#D1D5DB) - Empty stars
└── Urgent:      red-500    (#EF4444) - Optional highlight for p4
```

### 2.5 Dark Mode Colors

The application supports dark mode using Tailwind's `dark:` variant with class-based toggle.

```
Dark Mode Backgrounds
├── Page:        gray-900 (#111827) - Main page background
├── Surface:     gray-800 (#1F2937) - Cards, panels, modals
├── Elevated:    gray-700 (#374151) - Inputs, dropdowns
└── Navigation:  gray-800 (#1F2937) - Nav bar in dark mode

Dark Mode Text
├── Primary:     gray-100 (#F3F4F6) - Headings, important text
├── Secondary:   gray-300 (#D1D5DB) - Body text
├── Muted:       gray-400 (#9CA3AF) - Helper text, metadata
└── Placeholder: gray-500 (#6B7280) - Input placeholders

Dark Mode Borders
├── Default:     gray-700 (#374151) - Card borders, dividers
├── Subtle:      gray-600 (#4B5563) - Input borders
└── Focus:       teal-400 (#2DD4BF) - Focus rings

Dark Mode Status Badges
├── Pending:     yellow-900/30 bg, yellow-300 text
├── In Progress: blue-900/30 bg, blue-300 text
├── Completed:   green-900/30 bg, green-300 text
└── Overdue:     red-900/30 bg, red-300 text

Dark Mode Primary Button
├── Background:  teal-500 (#14B8A6)
└── Text:        gray-900 (#111827) - For contrast
```

### 2.6 Color Usage Rules

1. **Never rely on color alone** - Always pair with icons, text, or patterns
2. **Use semantic colors consistently** - Green always means success/completed
3. **Limit primary color usage** - Reserve teal for primary actions only
4. **Maintain contrast ratios** - Test all color combinations
5. **Use dark: variants** - Always include dark mode variants for new styles

---

## 3. Typography

### 3.1 Font Stack

```css
font-family: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
```

The application uses the system font stack via Tailwind CSS for optimal performance and native feel across platforms.

### 3.2 Type Scale

```
Headings
├── Page Title:   text-2xl (1.5rem/24px), font-bold, tracking-tight
├── Section:      text-xl  (1.25rem/20px), font-semibold
├── Subsection:   text-lg  (1.125rem/18px), font-semibold
└── Card Title:   text-base (1rem/16px), font-medium

Body
├── Default:      text-sm (0.875rem/14px), font-normal
├── Small:        text-xs (0.75rem/12px), font-normal
└── Large:        text-base (1rem/16px), font-normal

UI Elements
├── Button:       text-sm, font-semibold
├── Input:        text-sm, font-normal
├── Label:        text-sm, font-medium
├── Badge:        text-xs, font-medium
└── Helper:       text-xs, font-normal
```

### 3.3 Line Heights

```
Tight:    leading-tight  (1.25) - Headings
Normal:   leading-6      (1.5)  - Body text
Relaxed:  leading-7      (1.75) - Long-form content
```

### 3.4 Typography Rules

1. **Use semantic elements** - `<h1>` through `<h6>` for headings
2. **Single typeface** - Do not introduce additional fonts
3. **Maximum line length** - Limit to 75 characters for readability
4. **Consistent hierarchy** - Maintain visual hierarchy through size and weight

---

## 4. Spacing System

### 4.1 Base Unit

The spacing system uses a 4px base unit, aligned with Tailwind's default spacing scale.

### 4.2 Spacing Scale

```
Micro
├── 0.5:  2px   - Icon gaps, tight groupings
├── 1:    4px   - Element padding, inline spacing
├── 1.5:  6px   - Compact padding
└── 2:    8px   - Standard inline spacing

Small
├── 3:    12px  - Card internal padding (tight)
├── 4:    16px  - Standard card padding, form gaps
└── 5:    20px  - Section padding

Medium
├── 6:    24px  - Section margins, card gaps
├── 8:    32px  - Large section spacing
└── 10:   40px  - Major section gaps

Large
├── 12:   48px  - Page sections
├── 16:   64px  - Large page areas
└── 20:   80px  - Hero sections
```

### 4.3 Component Spacing

```
Cards
├── Padding:      p-4 (16px)
├── Gap:          gap-4 (16px) or space-y-3 (12px)
└── Margin:       mt-6 (24px) between card groups

Forms
├── Label margin: mb-1 (4px)
├── Input padding: px-3 py-2
├── Field gap:    gap-4 (16px)
└── Section gap:  space-y-6 (24px)

Lists
├── Item gap:     space-y-3 (12px)
├── Nested indent: ml-9 (36px) to align with checkbox
└── Group gap:    space-y-8 (32px)
```

### 4.4 Container Widths

```
max-w-7xl:  1280px - Main content container
max-w-lg:   512px  - Modal dialogs, auth forms
max-w-md:   448px  - Small dialogs
max-w-sm:   384px  - Dropdowns, popovers
```

### 4.5 Responsive Padding

```
Container: px-4 sm:px-6 lg:px-8
Section:   py-10 or py-6
```

---

## 5. Component Specifications

### 5.1 Buttons

**Primary Button**
```html
<button class="inline-flex items-center gap-2 rounded-md bg-teal-600 px-4 py-2
               text-sm font-semibold text-white shadow-sm
               hover:bg-teal-500
               focus-visible:outline focus-visible:outline-2
               focus-visible:outline-offset-2 focus-visible:outline-teal-600">
```

**Secondary Button**
```html
<button class="inline-flex items-center gap-2 rounded-md bg-white px-4 py-2
               text-sm font-semibold text-gray-900 shadow-sm
               ring-1 ring-inset ring-gray-300
               hover:bg-gray-50">
```

**Danger Button**
```html
<button class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2
               text-sm font-semibold text-white shadow-sm
               hover:bg-red-500">
```

**Ghost Button**
```html
<button class="inline-flex items-center gap-2 rounded-md px-4 py-2
               text-sm font-medium text-gray-700
               hover:bg-gray-100 hover:text-gray-900">
```

**Button Sizes**
```
Small:   px-3 py-1.5 text-xs
Default: px-4 py-2 text-sm
Large:   px-6 py-3 text-base
```

**Button States**
- **Disabled**: `opacity-50 cursor-not-allowed`
- **Loading**: Show spinner icon, disable pointer events

### 5.2 Form Inputs

**Text Input**
```html
<input type="text" class="block w-full rounded-md border-0 py-2 px-3
                          text-gray-900 shadow-sm
                          ring-1 ring-inset ring-gray-300
                          placeholder:text-gray-400
                          focus:ring-2 focus:ring-inset focus:ring-teal-600
                          sm:text-sm sm:leading-6">
```

**Select**
```html
<select class="block w-full rounded-md border-gray-300 py-2 pl-3 pr-10
               text-base focus:border-teal-500 focus:outline-none
               focus:ring-teal-500 sm:text-sm">
```

**Checkbox**
```html
<input type="checkbox" class="h-4 w-4 rounded border-gray-300
                              text-teal-600
                              focus:ring-teal-600">
```

**Input States**
- **Error**: `ring-red-300 focus:ring-red-500` with error message below
- **Disabled**: `bg-gray-50 text-gray-500 cursor-not-allowed`

### 5.3 Cards

**Standard Card**
```html
<div class="bg-white shadow rounded-lg p-4">
  <!-- Content -->
</div>
```

**Interactive Card**
```html
<div class="bg-white shadow rounded-lg p-4
            hover:shadow-md transition-shadow duration-200">
  <!-- Content -->
</div>
```

**Card with Header**
```html
<div class="bg-white shadow rounded-lg overflow-hidden">
  <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
    <h3 class="text-sm font-semibold text-gray-900">Title</h3>
  </div>
  <div class="p-4">
    <!-- Content -->
  </div>
</div>
```

### 5.4 Badges

**Status Badge**
```html
<span class="inline-flex items-center rounded-full px-2.5 py-0.5
             text-xs font-medium
             bg-{color}-100 text-{color}-800">
  Status
</span>
```

**Tag Badge**
```html
<span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5
             text-xs bg-teal-50 text-teal-600">
  <svg class="w-3 h-3">...</svg>
  Tag Name
</span>
```

### 5.5 Dropdowns

**Dropdown Menu**
```html
<div class="absolute right-0 z-10 mt-2 w-48 origin-top-right
            rounded-md bg-white py-1 shadow-lg
            ring-1 ring-black ring-opacity-5">
  <a class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
    Item
  </a>
</div>
```

**Dropdown Transitions** (Alpine.js)
```html
x-transition:enter="transition ease-out duration-100"
x-transition:enter-start="transform opacity-0 scale-95"
x-transition:enter-end="transform opacity-100 scale-100"
x-transition:leave="transition ease-in duration-75"
x-transition:leave-start="transform opacity-100 scale-100"
x-transition:leave-end="transform opacity-0 scale-95"
```

### 5.6 Modals

**Modal Structure**
```html
<div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
  <!-- Backdrop -->
  <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

  <!-- Centering wrapper -->
  <div class="flex min-h-screen items-center justify-center p-4">
    <!-- Modal panel -->
    <div class="relative transform overflow-hidden rounded-lg bg-white
                px-4 pb-4 pt-5 shadow-xl transition-all
                sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
      <!-- Content -->
    </div>
  </div>
</div>
```

**Modal Sizes**
```
Small:  sm:max-w-md  (448px)
Medium: sm:max-w-lg  (512px)
Large:  sm:max-w-xl  (576px)
XLarge: sm:max-w-2xl (672px)
```

### 5.7 Alerts/Toasts

**Flash Message Structure**
```html
<div class="rounded-md p-4 bg-{color}-50">
  <div class="flex">
    <div class="flex-shrink-0">
      <svg class="h-5 w-5 text-{color}-400">...</svg>
    </div>
    <div class="ml-3">
      <p class="text-sm font-medium text-{color}-800">Message</p>
    </div>
    <div class="ml-auto pl-3">
      <button class="inline-flex rounded-md p-1.5
                     bg-{color}-50 text-{color}-500 hover:bg-{color}-100">
        <svg class="h-5 w-5">...</svg>
      </button>
    </div>
  </div>
</div>
```

**Toast Auto-dismiss**
- Display duration: 5 seconds
- Use Alpine.js `x-init="setTimeout(() => show = false, 5000)"`

---

## 6. Task-Specific Components

### 6.1 Task Item

**Layout Structure**
```
┌─────────────────────────────────────────────────────────────┐
│ [○] Task Title                                    [⋮ menu] │
│     [Status] [★★★☆☆] [📅 Date] [📁 Project]               │
│     Description preview text (2 lines max)...               │
└─────────────────────────────────────────────────────────────┘
```

**Status Indicators**
```
Pending:     Border circle, gray-300 border
In Progress: Border circle with blue-500 fill/border
Completed:   Filled green-500 circle with white checkmark
```

**Checkbox Button**
```html
<!-- Pending/In Progress -->
<button class="w-6 h-6 rounded-full border-2 border-gray-300
               hover:border-green-500 hover:bg-green-50
               transition-colors">
</button>

<!-- Completed -->
<button class="w-6 h-6 rounded-full bg-green-500
               flex items-center justify-center
               hover:bg-green-600 transition-colors">
  <svg class="w-4 h-4 text-white">✓</svg>
</button>
```

### 6.2 Quick Add Input

**Input Field**
- Large, prominent input
- Placeholder: "What needs to be done?"
- Full width with rounded corners
- Focus state with indigo ring

**Parsed Highlights** (Future Phase 2)
```
Colors for inline highlights:
├── Date:     bg-blue-100, text-blue-700
├── Project:  bg-{project-color}-100
├── Tag:      bg-gray-100
├── Priority: bg-yellow-100
└── Invalid:  border-b-2 border-red-400 (underline)
```

### 6.3 Filter Panel

**Collapsed State**
- Show filter icon with "Filters" label
- Chevron indicating expandable
- Active filter count badge if filters applied

**Expanded State**
- Grid layout: 1 col mobile, 2 col tablet, 4 col desktop
- Each filter in own cell
- Apply button right-aligned
- Clear filters link when active

### 6.4 Empty States

**Structure**
```html
<div class="text-center py-12">
  <svg class="mx-auto h-12 w-12 text-gray-400">...</svg>
  <h3 class="mt-2 text-sm font-semibold text-gray-900">Title</h3>
  <p class="mt-1 text-sm text-gray-500">Description</p>
  <div class="mt-6">
    <button class="...">Call to Action</button>
  </div>
</div>
```

**Empty State Icons**
- No tasks: Clipboard with checkmark
- No results: Magnifying glass
- No projects: Folder

---

## 7. Layout Patterns

### 7.1 Page Structure

```
┌─────────────────────────────────────────────────────────────┐
│  Navigation Bar (bg-teal-600, fixed height h-16)          │
├─────────────────────────────────────────────────────────────┤
│  Flash Messages (if any)                                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│   Main Content Area (max-w-7xl, centered)                   │
│   ┌─────────────────────────────────────────────────────┐   │
│   │  Page Header (title, actions)                       │   │
│   │  Quick Add (sticky, always visible)                 │   │
│   │  Filters (collapsible)                              │   │
│   │  Content (task list, etc.)                          │   │
│   └─────────────────────────────────────────────────────┘   │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  Footer (optional, minimal)                                 │
└─────────────────────────────────────────────────────────────┘
```

### 7.2 Sidebar Layout (Future)

```
┌─────────────────────────────────────────────────────────────┐
│  Navigation Bar                                             │
├──────────────┬──────────────────────────────────────────────┤
│              │                                              │
│   Sidebar    │   Main Content                               │
│   (w-64)     │                                              │
│              │                                              │
│   - Views    │                                              │
│   - Projects │                                              │
│   - Tags     │                                              │
│              │                                              │
└──────────────┴──────────────────────────────────────────────┘
```

### 7.3 Responsive Breakpoints

```
sm:  640px  - Small tablets
md:  768px  - Tablets, small laptops
lg:  1024px - Laptops
xl:  1280px - Desktops
2xl: 1536px - Large screens
```

**Mobile-First Approach**
- Default styles for mobile
- Add complexity at larger breakpoints
- Hide sidebar on mobile (hamburger menu)
- Stack filters vertically on mobile

---

## 8. Icons

### 8.1 Icon System

Use inline SVG icons from Heroicons (https://heroicons.com/):
- **Outline style** for UI chrome (navigation, buttons)
- **Solid style** for status indicators

### 8.2 Icon Sizes

```
Micro:  w-3 h-3 (12px) - Inline with text, badges
Small:  w-4 h-4 (16px) - Buttons, inputs
Medium: w-5 h-5 (20px) - Navigation, standard UI
Large:  w-6 h-6 (24px) - Feature icons, emphasis
XLarge: w-12 h-12 (48px) - Empty states
```

### 8.3 Common Icons

```
Navigation
├── Home:        house
├── Tasks:       clipboard-document-list
├── Today:       calendar
├── Upcoming:    calendar-days
├── Search:      magnifying-glass
└── Settings:    cog-6-tooth

Actions
├── Add:         plus
├── Edit:        pencil
├── Delete:      trash
├── Filter:      funnel
├── Sort:        arrows-up-down
├── More:        ellipsis-vertical
└── Close:       x-mark

Status
├── Pending:     circle (outline)
├── In Progress: play-circle
├── Completed:   check-circle
└── Overdue:     exclamation-circle

Features
├── Project:     folder
├── Tag:         tag
├── Calendar:    calendar
├── Priority:    star
├── Recurring:   arrow-path
└── Subtasks:    list-bullet
```

---

## 9. Animation & Transitions

### 9.1 Duration Standards

```
Instant:  duration-75   (75ms)  - Micro-interactions
Fast:     duration-100  (100ms) - Dropdowns, tooltips
Normal:   duration-200  (200ms) - Most transitions
Slow:     duration-300  (300ms) - Modals, panels
```

### 9.2 Easing Functions

```
ease-out:    For elements entering (dropdowns opening)
ease-in:     For elements leaving (modals closing)
ease-in-out: For state changes (hover effects)
```

### 9.3 Common Animations

**Fade In/Out**
```html
x-transition:enter="transition ease-out duration-100"
x-transition:enter-start="opacity-0"
x-transition:enter-end="opacity-100"
x-transition:leave="transition ease-in duration-75"
x-transition:leave-start="opacity-100"
x-transition:leave-end="opacity-0"
```

**Scale + Fade (Dropdowns)**
```html
x-transition:enter="transition ease-out duration-100"
x-transition:enter-start="transform opacity-0 scale-95"
x-transition:enter-end="transform opacity-100 scale-100"
x-transition:leave="transition ease-in duration-75"
x-transition:leave-start="transform opacity-100 scale-100"
x-transition:leave-end="transform opacity-0 scale-95"
```

**Slide (Modals)**
```html
x-transition:enter="ease-out duration-300"
x-transition:enter-start="opacity-0 translate-y-4"
x-transition:enter-end="opacity-100 translate-y-0"
x-transition:leave="ease-in duration-200"
x-transition:leave-start="opacity-100 translate-y-0"
x-transition:leave-end="opacity-0 translate-y-4"
```

### 9.4 Hover Effects

```
Shadow lift:     hover:shadow-md transition-shadow duration-200
Background:      hover:bg-gray-100 transition-colors
Color change:    hover:text-gray-900 transition-colors
Border:          hover:border-gray-400 transition-colors
```

---

## 10. Accessibility Guidelines

### 10.1 Color Contrast

- **Text**: Minimum 4.5:1 contrast ratio
- **Large text (18px+)**: Minimum 3:1 contrast ratio
- **UI components**: Minimum 3:1 contrast ratio

### 10.2 Keyboard Navigation

- All interactive elements must be focusable
- Focus order follows logical reading order
- Visible focus indicators using `focus:ring-2 focus:ring-teal-600`
- Skip links for main content

### 10.3 Screen Readers

**Required Practices**
```html
<!-- Hidden labels for icon-only buttons -->
<button>
  <span class="sr-only">Delete task</span>
  <svg>...</svg>
</button>

<!-- Aria labels for regions -->
<nav aria-label="Main navigation">
<main aria-label="Task list">

<!-- Live regions for dynamic content -->
<div aria-live="polite" aria-atomic="true">
  Task completed successfully
</div>
```

### 10.4 Form Accessibility

```html
<!-- Always associate labels -->
<label for="title" class="...">Task title</label>
<input id="title" name="title" type="text">

<!-- Error messages -->
<input aria-describedby="title-error" aria-invalid="true">
<p id="title-error" class="text-red-600">Title is required</p>

<!-- Required fields -->
<input required aria-required="true">
```

### 10.5 Modal Accessibility

- `role="dialog"` and `aria-modal="true"`
- `aria-labelledby` pointing to modal title
- Focus trapped within modal
- Escape key closes modal
- Focus returns to trigger on close

---

## 11. Responsive Design

### 11.1 Mobile Patterns

**Navigation**
- Hamburger menu (hidden on desktop)
- User menu in mobile nav drawer
- Bottom sheet for filters (optional)

**Task List**
- Single column layout
- Swipe actions (future consideration)
- Larger touch targets (44x44px minimum)

**Forms**
- Full-width inputs
- Stacked layout
- Large submit buttons

### 11.2 Tablet Patterns

- 2-column filter grid
- Sidebar visible but collapsible
- Task cards wider but same structure

### 11.3 Desktop Patterns

- Full sidebar visible
- 4-column filter grid
- Keyboard shortcuts prominent

---

## 12. Dark Mode

The application fully supports dark mode using Tailwind's `dark:` variant with class-based toggle. Dark mode is controlled by adding/removing the `dark` class on the `<html>` element.

### 12.1 Theme Toggle Component

Located at `templates/components/theme-toggle.html.twig`, the theme toggle allows users to switch between:
- **Light** - Always use light mode
- **Dark** - Always use dark mode
- **System** - Follow the operating system preference

The theme preference is stored in:
1. `localStorage` for immediate access on page load
2. User settings (via API) for persistence across devices

### 12.2 Theme Initialization

To prevent flash of wrong theme on page load, the theme is initialized in a blocking `<script>` in the `<head>`:

```javascript
(function() {
    const theme = localStorage.getItem('theme') || 'system';
    const isDark = theme === 'dark' ||
        (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    if (isDark) {
        document.documentElement.classList.add('dark');
    }
})();
```

### 12.3 Dark Mode Patterns

**Cards/Containers:**
```html
class="bg-white dark:bg-gray-800 shadow dark:shadow-none ring-1 ring-gray-200/50 dark:ring-gray-700"
```

**Text:**
```html
class="text-gray-900 dark:text-gray-100"  <!-- Primary -->
class="text-gray-700 dark:text-gray-300"  <!-- Secondary -->
class="text-gray-500 dark:text-gray-400"  <!-- Muted -->
```

**Form Inputs:**
```html
class="bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 ring-gray-300 dark:ring-gray-600 focus:ring-teal-600 dark:focus:ring-teal-400"
```

**Buttons:**
```html
<!-- Primary -->
class="bg-teal-600 dark:bg-teal-500 text-white"
<!-- Secondary -->
class="bg-white dark:bg-gray-700 ring-gray-300 dark:ring-gray-600"
```

**Modal Backdrop:**
```html
class="bg-gray-500/75 dark:bg-gray-900/80"
```

**Toggle Switches:**
```html
:class="enabled ? 'bg-teal-600 dark:bg-teal-500' : 'bg-gray-200 dark:bg-gray-600'"
```

### 12.4 Settings Page Theme Selector

Users can also change their theme in Settings > Profile under the "Appearance" section. The selector shows three button options (Light, Dark, System) with visual icons.

---

## 13. Implementation Guidelines

### 13.1 Tailwind CSS Best Practices

1. **Use utility classes directly** - Avoid `@apply` except for highly reused patterns
2. **Consistent ordering** - Follow logical order: layout, sizing, spacing, colors, effects
3. **Responsive utilities** - Always mobile-first: `base md:desktop`
4. **Extract components** - Use Twig partials/components for repeated patterns

### 13.2 Alpine.js Patterns

```javascript
// Standard state pattern
x-data="{ open: false, loading: false }"

// Toggle pattern
@click="open = !open"

// Click-away pattern
@click.away="open = false"

// Init with cleanup
x-init="initFunction()" x-effect="cleanupEffect"
```

### 13.3 Component Organization

```
templates/
├── base.html.twig              # Base layout
├── components/                 # Reusable UI components
│   ├── button.html.twig
│   ├── card.html.twig
│   ├── modal.html.twig
│   ├── dropdown.html.twig
│   ├── filter-panel.html.twig
│   ├── quick-add.html.twig
│   └── toast.html.twig
├── task/
│   ├── _task_item.html.twig    # Task card partial
│   ├── list.html.twig
│   ├── today.html.twig
│   └── upcoming.html.twig
└── partials/                   # Layout partials
    ├── _navigation.html.twig
    ├── _sidebar.html.twig
    └── _footer.html.twig
```

### 13.4 CSS File Organization

```
assets/styles/
├── app.css                     # Entry point, imports
└── components/                 # Component-specific styles (if needed)
    └── quick-add.css          # Complex component styles
```

---

## 14. Quality Checklist

Before shipping any UI component, verify:

### Visual
- [ ] Follows color system exactly
- [ ] Typography matches specification
- [ ] Spacing is consistent
- [ ] Icons are correct size and style
- [ ] States (hover, focus, disabled) work correctly

### Interaction
- [ ] Transitions are smooth
- [ ] Animations use correct timing
- [ ] Loading states are handled
- [ ] Error states are handled

### Accessibility
- [ ] Keyboard navigable
- [ ] Screen reader tested
- [ ] Color contrast verified
- [ ] Focus indicators visible

### Responsive
- [ ] Mobile layout works
- [ ] Tablet layout works
- [ ] Desktop layout works
- [ ] No horizontal scroll

---

## Appendix A: Example Task Card HTML

```html
<div class="bg-white shadow rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
  <div class="flex items-start justify-between">
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-3">
        <!-- Status checkbox -->
        <button class="flex-shrink-0 w-6 h-6 rounded-full border-2 border-gray-300
                       hover:border-green-500 hover:bg-green-50 transition-colors">
        </button>

        <!-- Title -->
        <h3 class="text-sm font-medium text-gray-900 truncate">
          Review project proposal
        </h3>
      </div>

      <!-- Metadata row -->
      <div class="mt-2 flex flex-wrap items-center gap-2 ml-9">
        <!-- Status badge -->
        <span class="inline-flex items-center rounded-full bg-yellow-100
                     px-2.5 py-0.5 text-xs font-medium text-yellow-800">
          Pending
        </span>

        <!-- Priority stars -->
        <span class="inline-flex items-center text-xs text-gray-500">
          <svg class="w-3 h-3 text-yellow-400" fill="currentColor">★</svg>
          <svg class="w-3 h-3 text-yellow-400" fill="currentColor">★</svg>
          <svg class="w-3 h-3 text-yellow-400" fill="currentColor">★</svg>
          <svg class="w-3 h-3 text-gray-300" fill="currentColor">★</svg>
          <svg class="w-3 h-3 text-gray-300" fill="currentColor">★</svg>
        </span>

        <!-- Due date -->
        <span class="inline-flex items-center gap-1 text-xs text-gray-500">
          <svg class="w-3 h-3">📅</svg>
          Jan 25, 2026
        </span>

        <!-- Project badge -->
        <span class="inline-flex items-center gap-1 text-xs text-teal-600
                     bg-teal-50 rounded-full px-2 py-0.5">
          <svg class="w-3 h-3">📁</svg>
          Work
        </span>
      </div>

      <!-- Description -->
      <p class="mt-2 text-sm text-gray-500 line-clamp-2 ml-9">
        Review the Q1 project proposal and provide feedback...
      </p>
    </div>

    <!-- Actions menu -->
    <div class="flex-shrink-0 ml-4">
      <button class="inline-flex items-center rounded-md bg-white px-2 py-1
                     text-gray-400 hover:text-gray-600">
        <svg class="h-5 w-5">⋮</svg>
      </button>
    </div>
  </div>
</div>
```

---

## Appendix B: Color Reference Chart

### Light Mode

| Token | Hex | Usage |
|-------|-----|-------|
| teal-600 | #0D9488 | Primary actions, nav header |
| teal-500 | #14B8A6 | Primary hover |
| teal-100 | #CCFBF1 | Primary light bg |
| gray-900 | #111827 | Primary text |
| gray-700 | #374151 | Secondary text |
| gray-500 | #6B7280 | Muted text |
| gray-50 | #F9FAFB | Page background |
| yellow-400 | #FBBF24 | Pending, priority stars |
| blue-400 | #60A5FA | In progress |
| green-500 | #22C55E | Success, completed |
| red-500 | #EF4444 | Error, overdue, delete |

### Dark Mode

| Token | Hex | Usage |
|-------|-----|-------|
| teal-500 | #14B8A6 | Primary actions |
| teal-400 | #2DD4BF | Focus rings, accents |
| gray-900 | #111827 | Page background |
| gray-800 | #1F2937 | Card backgrounds, nav |
| gray-700 | #374151 | Input backgrounds |
| gray-100 | #F3F4F6 | Primary text |
| gray-300 | #D1D5DB | Secondary text |
| gray-400 | #9CA3AF | Muted text |
| yellow-300 | #FDE047 | Pending badge text |
| blue-300 | #93C5FD | In progress badge text |
| green-300 | #86EFAC | Completed badge text |
| red-300 | #FCA5A5 | Error badge text |

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-24 | Initial | Initial design system documentation |
| 1.1 | 2026-01-26 | Claude | Migrated primary color from indigo to teal, added dark mode support |
