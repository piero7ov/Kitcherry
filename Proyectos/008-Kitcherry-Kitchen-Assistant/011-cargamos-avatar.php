<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>A-Frame GLB Example</title>
  <script src="https://aframe.io/releases/1.6.0/aframe.min.js"></script>
</head>
<body>
  <a-scene>

    <!-- assets -->
    <a-assets>
      <a-asset-item id="avatar" src="avatar_completo.glb"></a-asset-item>
    </a-assets>

    <!-- light -->
    <a-entity light="type: directional; intensity: 1" position="2 4 2"></a-entity>
    <a-entity light="type: ambient; intensity: 0.5"></a-entity>

    <!-- plane -->
    <a-plane 
      rotation="-90 0 0" 
      width="10" 
      height="10" 
      color="#cccccc">
    </a-plane>

    <!-- model -->
    <a-entity 
      gltf-model="#avatar"
      position="0 1 -2"
      scale="2 2 2">
    </a-entity>

    <!-- camera -->
    <a-entity position="0 0.45 0.5">
      <a-camera fov="45"></a-camera>
    </a-entity>

  </a-scene>
</body>
</html>
