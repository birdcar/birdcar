import { useState } from "react";
import { motion } from "framer-motion";

const TAG_COLORS = [
  "green", "teal", "sapphire", "blue", "lavender", "mauve", "pink", "peach", "yellow", "sky",
] as const;

function colorForTag(tag: string): string {
  let hash = 0;
  for (let i = 0; i < tag.length; i++) {
    hash = ((hash << 5) - hash + tag.charCodeAt(i)) | 0;
  }
  return TAG_COLORS[Math.abs(hash) % TAG_COLORS.length];
}

interface Post {
  id: string;
  data: {
    title: string;
    date: Date;
    description?: string;
  };
  allTags: string[];
}

interface PostCardProps {
  post: Post;
  index: number;
}

export default function PostCard({ post, index }: PostCardProps) {
  const [isHovered, setIsHovered] = useState(false);

  return (
    <motion.li
      className="post-card"
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, delay: index * 0.08 }}
      whileHover={{ y: -3 }}
      onHoverStart={() => setIsHovered(true)}
      onHoverEnd={() => setIsHovered(false)}
    >
      <motion.div
        className="post-card__accent-bar"
        animate={{ opacity: isHovered ? 1 : 0 }}
        transition={{ duration: 0.25 }}
      />
      <a href={`/blog/${post.id}/`} className="post-card__link">
        <div className="post-card__header">
          <h3 className="post-card__title">{post.data.title}</h3>
          <time className="post-card__date" dateTime={post.data.date.toISOString()}>
            {post.data.date.toLocaleDateString("en-US", {
              year: "numeric",
              month: "short",
              day: "numeric",
            })}
          </time>
        </div>

        {post.data.description && (
          <p className="post-card__description">{post.data.description}</p>
        )}

        {post.allTags.length > 0 && (
          <ul className="post-card__tags">
            {post.allTags.map((tag, i) => (
              <motion.li
                key={tag}
                initial={{ opacity: 0, scale: 0.85 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ delay: index * 0.08 + i * 0.05 + 0.2 }}
              >
                <span className="post-card__tag" data-tag-color={colorForTag(tag)}>
                  #{tag}
                </span>
              </motion.li>
            ))}
          </ul>
        )}
      </a>
    </motion.li>
  );
}

interface PostListProps {
  posts: Post[];
}

export function PostList({ posts }: PostListProps) {
  return (
    <ul className="post-list">
      {posts.map((post, i) => (
        <PostCard key={post.id} post={post} index={i} />
      ))}
    </ul>
  );
}
