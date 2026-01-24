# UI-Related Phase Plan Modifications

This document identifies modifications needed to existing phase plans to incorporate the UI Design System guidelines.

---

## Overview

The UI Design System (`docs/UI-DESIGN-SYSTEM.md`) establishes standards that should be applied consistently across all phases. This document outlines specific changes needed to align existing phase plans with these standards.

---

## Phase 2: Natural Language Parsing

### Current State
Sub-Phase 2.7 describes the Quick Add UI but lacks specific visual specifications.

### Required Modifications

**2.7.2 Inline Highlighting** - Add specific color references:
```diff
- Highlight types:
- - Date: light blue background
- - Project: colored chip matching project color
- - Tag: gray chip
- - Priority: priority icon/badge
- - Invalid: red underline (for failed parses)

+ Highlight types (per UI-DESIGN-SYSTEM.md Section 6.2):
+ - Date:     bg-blue-100 text-blue-700, rounded px-1
+ - Project:  bg-{project-color}-100 text-{project-color}-700, rounded-full px-2 py-0.5
+ - Tag:      bg-gray-100 text-gray-700, rounded-full px-2 py-0.5
+ - Priority: bg-yellow-100 text-yellow-700, rounded px-1
+ - Invalid:  border-b-2 border-red-400 (underline style, not background)
```

**2.7.3 Project Autocomplete Dropdown** - Add styling specifications:
```diff
+ AUTOCOMPLETE DROPDOWN STYLING:
+ - Position: absolute, z-10, below input
+ - Container: rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5
+ - Items: px-4 py-2 text-sm text-gray-700 hover:bg-gray-100
+ - Selected: bg-indigo-50 text-indigo-700
+ - Project color indicator: w-3 h-3 rounded-full inline-block
+ - Hierarchy indent: pl-4 per level
+ - Max height: max-h-60 overflow-y-auto
```

**2.7.6 Quick Add Template** - Reference design system:
```diff
+ QUICK ADD STYLING (per UI-DESIGN-SYSTEM.md Section 6.2):
+ - Input: text-base (larger than standard inputs for prominence)
+ - Placeholder: text-gray-400 "What needs to be done?"
+ - Container: bg-white shadow rounded-lg p-4 (card pattern)
+ - Button: Primary button style, inline-flex with plus icon
+ - Keyboard hint: text-xs text-gray-400 below input
```

### New Additions

Add new task **2.7.8 Apply UI Design System**:
```markdown
- [ ] **2.7.8** Apply UI Design System standards to Quick Add
  - Reference `docs/UI-DESIGN-SYSTEM.md` for all styling decisions
  - Use established color tokens (Section 2)
  - Follow component specifications (Section 5)
  - Implement proper transitions (Section 9)
  - Verify accessibility compliance (Section 10)
```

---

## Phase 3: Project Hierarchy

### Required Modifications

**Project Tree UI** - Add visual specifications:
```diff
+ PROJECT TREE VISUAL SPECIFICATIONS:
+ - Indentation: pl-4 (16px) per nesting level
+ - Collapse/expand icon: w-4 h-4, chevron-right/chevron-down
+ - Project color: w-3 h-3 rounded-full indicator before name
+ - Task count: text-xs text-gray-500, parenthetical after name
+ - Hover state: bg-gray-50 rounded-md
+ - Active state: bg-indigo-50 text-indigo-700
+ - Archived projects: text-gray-400, italic
```

---

## Phase 4: Views & Filtering

### Current State
Sub-Phase 4.7 describes filter UI but lacks specific component styling references.

### Required Modifications

**4.1.4 Overdue Visual Indicators** - Align with design system:
```diff
- /* Overdue task styling */
- .task-overdue {
-   border-left: 3px solid #e74c3c;
- }
- .task-overdue .due-date {
-   color: #e74c3c;
- }

+ OVERDUE STYLING (per UI-DESIGN-SYSTEM.md):
+ - Task card: border-l-4 border-red-500 (4px left border)
+ - Due date text: text-red-600 font-medium
+ - Overdue badge: bg-red-100 text-red-800 rounded-full px-2.5 py-0.5 text-xs
+ - Calendar icon: text-red-500
```

**4.2.4 Upcoming View Template** - Add grouping styles:
```diff
+ UPCOMING VIEW GROUP STYLING:
+ - Group header: text-sm font-semibold text-gray-900 py-2 sticky top-0 bg-gray-50
+ - Today header: bg-indigo-50 text-indigo-700
+ - Tomorrow header: standard gray
+ - Overdue header: bg-red-50 text-red-700
+ - Date format: "Today", "Tomorrow", "Fri, Jan 24", "Next Week"
+ - Collapsible: chevron icon, x-show with transition
```

**4.3.4 Overdue View Template** - Add severity styling:
```diff
+ OVERDUE SEVERITY STYLING:
+ - Low (1-2 days): border-l-4 border-yellow-400
+ - Medium (3-7 days): border-l-4 border-orange-500
+ - High (7+ days): border-l-4 border-red-600
+ - Severity badge colors match border colors
```

**4.7.1 Filter Panel Component** - Detailed styling:
```diff
+ FILTER PANEL STYLING (per UI-DESIGN-SYSTEM.md):
+ - Container: bg-white shadow rounded-lg p-4
+ - Collapsed header: flex items-center justify-between, cursor-pointer
+ - Filter icon: w-5 h-5 text-gray-500
+ - Chevron: w-4 h-4, rotate-180 when expanded
+ - Grid: grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4
+ - Labels: text-sm font-medium text-gray-700
+ - Select inputs: use form input styles from Section 5.2
+ - Apply button: Primary button style
+ - Clear link: text-sm text-indigo-600 hover:text-indigo-500
```

**4.7.3 Active Filters Display** - Component specification:
```diff
+ ACTIVE FILTERS STYLING:
+ - Container: flex flex-wrap gap-2 mt-4
+ - Filter chip: inline-flex items-center gap-1 rounded-full px-3 py-1
+               bg-gray-100 text-gray-700 text-sm
+ - Remove button: w-4 h-4 text-gray-400 hover:text-gray-600
+ - Clear all: text-sm text-indigo-600 hover:text-indigo-500, ml-2
+ - Transition: x-transition for smooth add/remove
```

**4.7.4 Sort Dropdown** - Component specification:
```diff
+ SORT DROPDOWN STYLING:
+ - Trigger: Secondary button style with arrows-up-down icon
+ - Current sort indicator: font-medium in trigger text
+ - Dropdown: standard dropdown styling (Section 5.5)
+ - Sort options: px-4 py-2 text-sm text-gray-700 hover:bg-gray-100
+ - Active sort: bg-indigo-50 text-indigo-700 with checkmark icon
+ - Direction toggle: asc/desc icons beside each option
```

### New Additions

Add task **4.7.9 Verify UI Design System Compliance**:
```markdown
- [ ] **4.7.9** Verify UI Design System compliance
  - All filter components match design system specifications
  - Color usage follows semantic color rules
  - Spacing uses defined spacing scale
  - Transitions follow animation standards
  - Accessibility checklist completed for all components
```

---

## Phase 5: Recurring Tasks

### Required Modifications

**Recurring Task Indicator** - Add visual specification:
```diff
+ RECURRING TASK INDICATOR:
+ - Icon: arrow-path (Heroicons), w-4 h-4 text-gray-400
+ - Position: in metadata row, after priority stars
+ - Tooltip: shows recurrence_rule text
+ - When editing: show editable recurrence field with examples
```

---

## Phase 7: Search & Undo

### Required Modifications

**Undo Toast** - Align with design system:
```diff
+ UNDO TOAST STYLING (per UI-DESIGN-SYSTEM.md Section 5.7):
+ - Position: fixed bottom-4 right-4 z-50
+ - Container: bg-gray-900 text-white rounded-lg shadow-lg px-4 py-3
+ - Message: text-sm
+ - Undo button: text-indigo-400 hover:text-indigo-300 font-medium ml-4
+ - Countdown: text-xs text-gray-400, shows seconds remaining
+ - Transition: slide-up and fade, duration-300
+ - Auto-dismiss: 5 seconds (matches design system toast standard)
```

**Search Results Highlighting** - Visual specification:
```diff
+ SEARCH RESULT HIGHLIGHTING:
+ - Match highlight: bg-yellow-200 rounded px-0.5
+ - Result item: standard task card with matches highlighted
+ - Section headers: "Tasks" and "Projects" with counts
+ - Empty state: use standard empty state pattern (Section 6.4)
```

---

## Phase 8: Polish & Testing

### Required Modifications

**Mobile Responsiveness** - Reference design system:
```diff
+ MOBILE RESPONSIVE IMPLEMENTATION:
+ Reference UI-DESIGN-SYSTEM.md Section 11 for all mobile patterns:
+ - Navigation: hamburger menu pattern
+ - Sidebar: collapsible/drawer pattern
+ - Task list: single column layout
+ - Touch targets: minimum 44x44px
+ - Filter panel: full-width, stacked vertically
```

**Keyboard Shortcuts** - UI representation:
```diff
+ KEYBOARD SHORTCUT UI:
+ - Help modal trigger: ? key
+ - Modal: max-w-lg, lists all shortcuts in table format
+ - Shortcut key: bg-gray-100 rounded px-2 py-1 font-mono text-sm
+ - Description: text-sm text-gray-600
+ - Sections: Navigation, Tasks, Quick Add, Views
```

### New Addition

Add task **8.X UI Design System Audit**:
```markdown
- [ ] **8.X** UI Design System audit
  - Audit all existing templates against design system
  - Create list of deviations
  - Fix inconsistencies
  - Document any intentional deviations in DEVIATIONS.md
  - Update design system if patterns evolved
```

---

## Cross-Phase Considerations

### Template Component Library

Before Phase 4 begins, create reusable Twig components:

```
templates/components/
├── button.html.twig          # Primary, secondary, danger, ghost variants
├── input.html.twig           # Text, select, checkbox with error states
├── card.html.twig            # Standard, interactive, with-header variants
├── badge.html.twig           # Status badges with color variants
├── dropdown.html.twig        # Reusable dropdown with Alpine.js
├── modal.html.twig           # Standard modal structure
├── toast.html.twig           # Flash message / toast component
├── empty-state.html.twig     # Reusable empty state pattern
└── icon.html.twig            # SVG icon helper (optional)
```

### Alpine.js State Patterns

Establish consistent Alpine.js patterns used across phases:

```javascript
// Standard component patterns to use throughout
{
  // Dropdown pattern
  x-data="{ open: false }"
  @click.away="open = false"

  // Modal pattern
  x-data="{ showModal: false }"
  @keydown.escape.window="showModal = false"

  // Loading pattern
  x-data="{ loading: false }"
  :disabled="loading"
  :class="{ 'opacity-50 cursor-not-allowed': loading }"

  // Toast auto-dismiss
  x-init="setTimeout(() => show = false, 5000)"
}
```

---

## Implementation Priority

When implementing UI components, prioritize in this order:

1. **Task Card** (Phase 1 foundation, used everywhere)
2. **Quick Add** (Phase 2 primary feature)
3. **Filter Panel** (Phase 4 core functionality)
4. **View Templates** (Phase 4 specialized views)
5. **Modals/Toasts** (Phase 7 undo system)
6. **Mobile Optimization** (Phase 8 polish)

---

## Review Process

For each phase, UI review should verify:

1. **Design System Compliance**
   - Colors match design tokens
   - Typography follows scale
   - Spacing uses defined scale
   - Components match specifications

2. **Consistency**
   - Similar elements look the same
   - Patterns are reused appropriately
   - No one-off styling

3. **Accessibility**
   - Color contrast verified
   - Keyboard navigation works
   - Screen reader tested
   - Focus states visible

4. **Responsiveness**
   - Mobile breakpoint tested
   - Tablet breakpoint tested
   - Desktop layout correct

---

## Document History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-24 | Initial modifications document |
