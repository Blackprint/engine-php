<?php
namespace Blackprint;

class Tools {
    /**
     * Extract skeleton from Blackprint nodes
     * @return string JSON string containing nodes structure
     */
    public static function extractSkeleton(): string {
        // $docs = Blackprint::_docs ?? [];
        $nodes = [];
        $virtualType = [];

        function vType($type): string {
            if (isset($type['virtualType'])) {
                return implode(',', array_map(function($v) { return $v->name; }, $type['virtualType']));
            }
            if (isset($type['type'])) {
                $type = $type['type'];
            }

			if ($type === Types::Number) return 'Number';
			else if ($type === Types::String) return 'String';
			else if ($type === Types::Object) return 'Object';
			else if ($type === Types::Array) return 'Array';
			else if ($type === Types::Boolean) return 'Boolean';
            else if ($type === Types::Any) return 'BP.Any';
            else if ($type === Types::Trigger) return 'BP.Trigger';
            else if ($type === Types::Route) return 'BP.Route';

            if (is_array($type)) {
                $types = [];
                for ($i = 0; $i < count($type); $i++) {
                    $types[$i] = vType($type[$i]);
                }
                return implode(',', $types);
            }

            $existIndex = array_search($type, $virtualType);
            if ($existIndex === false) {
                $existIndex = count($virtualType);
                $virtualType[] = $type;
            }

            return $type . '.' . $existIndex;
        }

        // Recursively process nested structure
        function deep($nest, &$save, $first=false) {
            foreach ($nest as $key => &$ref) {
                if (isset($ref->hidden) && $ref->hidden) continue;
				if($first &&$key === 'BP') continue;

                if (is_array($ref) || is_object($ref)) {
                    if (!isset($save[$key])) {
                        $save[$key] = [];
                    }
                    deep($ref, $save[$key]);
                    continue;
                }
                // else .. ref == class

                if (!isset($save[$key])) {
                    $save[$key] = [];
                }
                $temp = &$save[$key];
                $temp['Input'] = [];
                $temp['Output'] = [];

                foreach ($temp as $which => &$target) {
                    if (!isset($ref::$$which)) continue;

                    $refTarget = $ref::$$which;
                    foreach ($refTarget as $name => &$type) {
                        $savePort = &$temp[$which];

                        if (is_array($type) && isset($type['feature'])) {
                            if ($type['feature'] === PortType::ArrayOf)
                                $savePort[$name] = 'BP.ArrayOf<' . vType($type) . '>';
                            else if ($type['feature'] === PortType::StructOf)
                                $savePort[$name] = 'BP.StructOf<' . vType($type) . '>';
                            else if ($type['feature'] === PortType::Trigger)
                                $savePort[$name] = 'BP.Trigger';
                            else if ($type['feature'] === PortType::Union)
                                $savePort[$name] = 'BP.Union<' . vType($type) . '>';
                            else if ($type['feature'] === PortType::VirtualType)
                                $savePort[$name] = 'VirtualType<' . vType($type) . '>';
							else throw new \Exception("Port feature not supported");
                        }
						else if ($type === Types::Number) $savePort[$name] = 'Number';
						else if ($type === Types::String) $savePort[$name] = 'String';
						else if ($type === Types::Object) $savePort[$name] = 'Object';
						else if ($type === Types::Array) $savePort[$name] = 'Array';
						else if ($type === Types::Boolean) $savePort[$name] = 'Boolean';
                        else if ($type === Types::Any) $savePort[$name] = 'BP.Any';
                        else if ($type === Types::Trigger) $savePort[$name] = 'BP.Trigger';
                        else if ($type === Types::Route) $savePort[$name] = 'BP.Route';
						else $savePort[$name] = 'VirtualType<' . vType($type) . '>';
                    }
                }

                // Rename
                $temp['$input'] = $temp['Input'];
                $temp['$output'] = $temp['Output'];

                unset($temp['Input']);
                unset($temp['Output']);
            }
        }

        deep(Internal::$nodes, $nodes, true);

        return json_encode([
            'nodes' => $nodes,
            // 'docs' => $docs,
        ]);
    }
}