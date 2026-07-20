const fs = require('fs');

const sections = [
  { file: 'resources/js/Components/Home/TestimonialsSection.vue', type: 'bg-gray-50', replace: 'sectionBgClass' },
  { file: 'resources/js/Components/Home/FaqSection.vue', type: 'bg-white', replace: 'sectionBgClass' },
  { file: 'resources/js/Components/Home/StatsBarSection.vue', type: 'var(--color-bg)', replace: 'sectionBgClass' },
  { file: 'resources/js/Components/Home/LatestPostsSection.vue', type: 'bg-gray-50', replace: 'sectionBgClass' },
  { file: 'resources/js/Components/Home/NewsletterSection.vue', type: 'var(--color-bg)', replace: 'sectionBgClass' },
  { file: 'resources/js/Components/Home/RichtextSection.vue', type: 'var(--color-bg)', replace: 'sectionBgClass' },
  { file: 'resources/js/Components/Home/ImageCarouselSection.vue', type: 'var(--color-bg)', replace: 'sectionBgClass' },
  { file: 'resources/js/Components/Home/AnnouncementSection.vue', type: 'var(--color-bg)', replace: 'sectionBgClass' },
  { file: 'resources/js/Components/Home/AllToolsSection.vue', type: 'var(--color-bg)', replace: 'sectionBgClass' },
];

for (const s of sections) {
  let c = fs.readFileSync(s.file, 'utf8');

  // Add import if missing
  if (!c.includes('useSectionStyle')) {
    c = c.replace(
      "import { useTranslate } from '@/Composables/useTranslate'",
      "import { useTranslate } from '@/Composables/useTranslate'\nimport { useSectionStyle } from '@/Composables/useSectionStyle'\nconst { sectionBgClass, titleColorClass, subtitleColorClass, titleAlignClass, titleSizeClass, badgeClass } = useSectionStyle()"
    );
  }

  // Replace <section class="...bg...  with  <section :class="[sectionBgClass(...)]" class="...
  if (s.type === 'bg-gray-50') {
    c = c.replace(/<section class="bg-gray-50 py-24(?: transition-colors duration-300)? dark:bg-surface-900">/g,
      '<section :class="[sectionBgClass(asString(section.config.section_bg, \'default\'))]" class="py-24 transition-colors duration-300">');
    c = c.replace(/<section class="bg-gray-50 py-24 dark:bg-surface-900">/g,
      '<section :class="[sectionBgClass(asString(section.config.section_bg, \'default\'))]" class="py-24">');
  } else if (s.type === 'bg-white') {
    c = c.replace(/<section class="bg-white py-24(?: transition-colors duration-300)? dark:bg-surface-950">/g,
      '<section :class="[sectionBgClass(asString(section.config.section_bg, \'default\'))]" class="py-24 transition-colors duration-300">');
  } else if (s.type === 'var(--color-bg)') {
    c = c.replace(/<section class="bg-\[var\(--color-bg\)\] py-24">/g,
      '<section :class="[sectionBgClass(asString(section.config.section_bg, \'default\'))]" class="py-24">');
    c = c.replace(/<section class="bg-\[var\(--color-bg\)\] py-16">/g,
      '<section :class="[sectionBgClass(asString(section.config.section_bg, \'default\'))]" class="py-16">');
    c = c.replace(/<section class="border-y border-gray-100 bg-\[var\(--color-bg\)\] py-16">/g,
      '<section :class="[sectionBgClass(asString(section.config.section_bg, \'default\'))]" class="border-y border-gray-100 py-16">');
  }

  fs.writeFileSync(s.file, c);
  console.log('Updated: ' + s.file);
}
