import type {ReactNode} from 'react';
import clsx from 'clsx';
import Heading from '@theme/Heading';
import styles from './styles.module.css';

type FeatureItem = {
  title: string;
  description: ReactNode;
};

const FeatureList: FeatureItem[] = [
  {
    title: 'Fluent API',
    description: (
      <>
        Chain <code>addVideoStream()</code>, <code>addAudioStream()</code>,
        and output methods together for a Laravel-style, readable way to
        drive Shaka Packager.
      </>
    ),
  },
  {
    title: 'Adaptive bitrate, HLS & DASH',
    description: (
      <>
        Package multi-quality streams and generate both HLS and DASH output
        from the same fluent builder, across local, S3, or custom
        filesystem disks.
      </>
    ),
  },
  {
    title: 'Encryption & DRM',
    description: (
      <>
        Built-in AES-128 encryption with key rotation, plus dynamic URL
        resolvers for serving signed keys and segments from private
        storage.
      </>
    ),
  },
];

function Feature({title, description}: FeatureItem) {
  return (
    <div className={clsx('col col--4')}>
      <div className="text--center padding-horiz--md">
        <Heading as="h3">{title}</Heading>
        <p>{description}</p>
      </div>
    </div>
  );
}

export default function HomepageFeatures(): ReactNode {
  return (
    <section className={styles.features}>
      <div className="container">
        <div className="row">
          {FeatureList.map((props, idx) => (
            <Feature key={idx} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}
