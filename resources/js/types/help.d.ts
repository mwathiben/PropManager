/**
 * Help Domain Type Definitions
 * Interfaces for FAQs, Help Articles, and Help Categories
 */

import type { BaseEntity } from './finances';

// Help Category
export type HelpCategory =
  | 'getting-started'
  | 'features'
  | 'billing'
  | 'troubleshooting'
  | 'notifications'
  | 'security';

// FAQ Item
export interface FAQ extends BaseEntity {
  category: HelpCategory;
  question: string;
  answer: string;
  order: number;
  is_published: boolean;
}

// Help Article
export interface HelpArticle extends BaseEntity {
  category: HelpCategory;
  title: string;
  slug: string;
  excerpt?: string;
  content: string;
  order: number;
  is_published: boolean;
  views_count?: number;
  helpful_count?: number;
  not_helpful_count?: number;
}

// Category Info
export interface CategoryInfo {
  id: HelpCategory;
  name: string;
  description?: string;
  icon?: string;
  article_count?: number;
  faq_count?: number;
}

// Search Results
export interface HelpSearchResults {
  faqs: FAQ[];
  articles: HelpArticle[];
}

// Help Index Page Props
export interface HelpIndexPageProps {
  faqs: Record<HelpCategory, FAQ[]>;
  articles: Record<HelpCategory, HelpArticle[]>;
  categories: Record<HelpCategory, CategoryInfo>;
  supportEmail: string;
}

// Help Show Page Props (single article)
export interface HelpShowPageProps {
  article: HelpArticle;
  relatedArticles?: HelpArticle[];
  category: CategoryInfo;
}
