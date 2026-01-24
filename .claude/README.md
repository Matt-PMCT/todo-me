# Claude Code Configuration

This directory contains Claude Code skills and agents for the todo-me project.

## Directory Structure

```
.claude/
├── README.md           # This file
├── skills/             # Reusable skills (portable expertise)
│   └── styling-ui/     # UI Design System skill
│       ├── SKILL.md    # Main skill file
│       ├── COMPONENTS.md # Component specifications
│       └── PATTERNS.md # Interaction patterns
└── agents/             # Custom subagents (future)
```

## Skills

Skills are portable expertise that any Claude instance can use. They provide domain-specific knowledge and guidance.

### Using Skills

Skills are automatically discovered by Claude Code. You can also invoke them directly:

```
/styling-ui
```

### Available Skills

| Skill | Description |
|-------|-------------|
| `styling-ui` | UI Design System guidance for Tailwind CSS, Alpine.js, and accessibility |

### Creating New Skills

1. Create a folder in `.claude/skills/` with your skill name
2. Add a `SKILL.md` file with YAML frontmatter and instructions
3. Optionally add reference files for detailed specifications

See [Claude Skills Documentation](https://docs.anthropic.com/en/docs/claude-code/skills) for more details.

## Agents

Custom subagents for specialized workflows.

### Available Agents

| Agent | Description |
|-------|-------------|
| `previous-commit-reviewer` | Reviews the previous commit for plan alignment, code quality, tests, and safety |
| `root-cause-investigator` | Investigates bugs using 5-Why methodology for systematic root cause analysis |

## Git Tracking

Skills and agents in this directory **are tracked by git** and shared with the team. Only user-specific settings (`.claude/*.local.*`) are ignored.

## Related Documentation

- `docs/UI-DESIGN-SYSTEM.md` - Full UI design system specification
- `docs/UI-PHASE-MODIFICATIONS.md` - Phase-specific UI requirements
- `CLAUDE.md` - Project-wide Claude instructions
