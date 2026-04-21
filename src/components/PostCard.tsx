import { motion } from "framer-motion";

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
  featured?: boolean;
}

export default function PostCard({ post, index, featured = false }: PostCardProps) {
  return (
    <motion.li
      className={featured ? "post-card post-card--featured" : "post-card"}
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4, delay: index * 0.08 }}
      whileHover={{ y: -3 }}
    >
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
                <span className="post-card__tag">#{tag}</span>
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
  featuredFirst?: boolean;
}

export function PostList({ posts, featuredFirst = false }: PostListProps) {
  return (
    <ul className="post-list">
      {posts.map((post, i) => (
        <PostCard
          key={post.id}
          post={post}
          index={i}
          featured={featuredFirst && i === 0}
        />
      ))}
    </ul>
  );
}
